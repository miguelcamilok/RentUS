<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Listado con filtros y paginación
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $query = Property::query();

        // Filtros
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->has('city')) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        if ($request->has('min_price')) {
            $query->where('monthly_price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('monthly_price', '<=', $request->max_price);
        }

        // Ordenamiento
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortFields = [
            'id', 'title', 'city', 'monthly_price', 'area_m2',
            'views', 'created_at', 'updated_at', 'status', 'approval_status'
        ];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Eager loading con imágenes
        $query->with([
            'user:id,name,email,phone,photo',
            'images' => function ($q) {
                $q->orderBy('order');
            }
        ]);

        $properties = $query->paginate($perPage);

        return response()->json([
            'data' => $properties->items(),
            'meta' => [
                'current_page' => $properties->currentPage(),
                'last_page' => $properties->lastPage(),
                'per_page' => $properties->perPage(),
                'total' => $properties->total(),
            ]
        ]);
    }

    /**
     * Mostrar propiedad por ID
     */
    public function show(Property $property)
    {
        $property->load([
            'user:id,name,email,phone,photo',
            'images' => function ($q) {
                $q->orderBy('order');
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $property
        ]);
    }

    /**
     * 🔥 CREAR PROPIEDAD CON IMÁGENES BASE64
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title'             => 'required|string|max:255',
                'description'       => 'required|string',
                'address'           => 'required|string',
                'city'              => 'nullable|string|max:120',
                'status'            => 'nullable|string|in:available,rented,maintenance',
                'monthly_price'     => 'required|numeric|min:0',
                'area_m2'           => 'nullable|numeric|min:0',
                'num_bedrooms'      => 'nullable|integer|min:0',
                'num_bathrooms'     => 'nullable|integer|min:0',
                'included_services' => 'nullable|string',
                'lat'               => 'nullable|numeric',
                'lng'               => 'nullable|numeric',
                'accuracy'          => 'nullable|numeric',
                'user_id'           => 'nullable|integer|exists:users,id',
                'images'            => 'nullable|string', // JSON array de base64
                'publication_date'  => 'nullable|date',
            ]);

            DB::beginTransaction();

            // Procesar included_services
            if (isset($validated['included_services'])) {
                if (is_string($validated['included_services'])) {
                    $decoded = json_decode($validated['included_services'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $validated['included_services'] = '[]';
                    }
                }
            } else {
                $validated['included_services'] = '[]';
            }

            // Asignar user_id
            if (!isset($validated['user_id'])) {
                $validated['user_id'] = auth()->id();
            }

            // Asignar publication_date
            if (!isset($validated['publication_date']) || empty($validated['publication_date'])) {
                $validated['publication_date'] = now()->format('Y-m-d');
            }

            // Auto-aprobar si es admin/support
            $user = auth()->user();
            if ($user && in_array($user->role, ['admin', 'support'])) {
                $validated['approval_status'] = 'approved';
                $validated['visibility'] = 'published';
            }

            // Extraer imágenes antes de crear propiedad
            $imagesData = $validated['images'] ?? null;
            unset($validated['images']);
            $validated['image_url'] = null;

            Log::info('📝 Creando propiedad:', [
                'title' => $validated['title'],
                'user_id' => $validated['user_id'],
                'tiene_imagenes' => !empty($imagesData)
            ]);

            // Crear propiedad
            $property = Property::create($validated);

            Log::info('✅ Propiedad creada con ID: ' . $property->id);

            // 🔥 PROCESAR IMÁGENES BASE64
            if ($imagesData) {
                $imagesArray = json_decode($imagesData, true);

                if (is_array($imagesArray) && count($imagesArray) > 0) {
                    Log::info('📸 Procesando ' . count($imagesArray) . ' imágenes');

                    foreach ($imagesArray as $index => $base64Image) {
                        try {
                            // Validar formato Data URI
                            if (!str_starts_with($base64Image, 'data:image/')) {
                                Log::warning("⚠️ Imagen {$index} no es Data URI válido");
                                continue;
                            }

                            // Validar tamaño aproximado (Base64 es ~33% más grande)
                            // 2MB original = ~2.7MB base64
                            $estimatedSize = (strlen($base64Image) * 0.75) / (1024 * 1024);
                            if ($estimatedSize > 3) {
                                Log::warning("⚠️ Imagen {$index} muy grande: {$estimatedSize}MB");
                                continue;
                            }

                            // Crear registro en property_images
                            PropertyImage::create([
                                'property_id' => $property->id,
                                'image_url' => $base64Image,
                                'order' => $index,
                                'is_main' => $index === 0
                            ]);

                            Log::info("✅ Imagen {$index} guardada");

                            // Si es la primera imagen, actualizar image_url principal
                            if ($index === 0) {
                                $property->update(['image_url' => $base64Image]);
                                Log::info("✅ image_url principal actualizado");
                            }
                        } catch (\Exception $e) {
                            Log::error("❌ Error procesando imagen {$index}: " . $e->getMessage());
                        }
                    }
                } else {
                    Log::warning('⚠️ El campo images no contiene un array válido');
                }
            } else {
                Log::info('ℹ️ No se proporcionaron imágenes');
            }

            DB::commit();

            // Cargar relaciones
            $property->load([
                'user:id,name,email,phone,photo',
                'images' => function ($q) {
                    $q->orderBy('order');
                }
            ]);

            Log::info('🎉 Propiedad creada con ' . $property->images->count() . ' imágenes');

            return response()->json([
                'success'  => true,
                'message'  => 'Propiedad creada exitosamente',
                'property' => $property
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('❌ Error de validación:', $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error creando propiedad: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la propiedad',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * 🔥 ACTUALIZAR PROPIEDAD CON IMÁGENES BASE64
     */
    public function update(Request $request, Property $property)
    {
        // Validación de permiso
        $user = auth()->user();
        $isOwner = $property->user_id === $user->id;
        $isAdmin = in_array($user->role, ['admin', 'support']);

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para editar esta propiedad'
            ], 403);
        }

        $validated = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'description'       => 'sometimes|string',
            'address'           => 'sometimes|string',
            'city'              => 'sometimes|string|max:120',
            'status'            => 'sometimes|string|in:available,rented,maintenance',
            'monthly_price'     => 'sometimes|numeric|min:0',
            'area_m2'           => 'sometimes|numeric|min:0',
            'num_bedrooms'      => 'sometimes|integer|min:0',
            'num_bathrooms'     => 'sometimes|integer|min:0',
            'included_services' => 'sometimes|string',
            'lat'               => 'sometimes|numeric',
            'lng'               => 'sometimes|numeric',
            'images'            => 'sometimes|string', // Nuevas imágenes base64
            'delete_images'     => 'sometimes|array', // IDs a eliminar
            'delete_images.*'   => 'integer|exists:property_images,id',
            'reorder_images'    => 'sometimes|array', // Reordenar existentes
        ]);

        try {
            DB::beginTransaction();

            // Parsear included_services
            if (isset($validated['included_services']) && is_string($validated['included_services'])) {
                $decoded = json_decode($validated['included_services'], true);
                $validated['included_services'] = is_array($decoded) ? json_encode($decoded) : '[]';
            }

            // Eliminar imágenes marcadas
            if (isset($validated['delete_images'])) {
                PropertyImage::whereIn('id', $validated['delete_images'])
                    ->where('property_id', $property->id)
                    ->delete();
            }

            // Reordenar imágenes existentes
            if (isset($validated['reorder_images'])) {
                foreach ($validated['reorder_images'] as $imageId => $newOrder) {
                    PropertyImage::where('id', $imageId)
                        ->where('property_id', $property->id)
                        ->update(['order' => $newOrder]);
                }
            }

            // Agregar nuevas imágenes
            if (isset($validated['images'])) {
                $imagesArray = json_decode($validated['images'], true);

                if (is_array($imagesArray) && count($imagesArray) > 0) {
                    $currentMaxOrder = PropertyImage::where('property_id', $property->id)
                        ->max('order') ?? -1;

                    foreach ($imagesArray as $index => $base64Image) {
                        try {
                            if (str_starts_with($base64Image, 'data:image/')) {
                                PropertyImage::create([
                                    'property_id' => $property->id,
                                    'image_url' => $base64Image,
                                    'order' => $currentMaxOrder + $index + 1,
                                    'is_main' => false
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Error procesando imagen ' . $index . ': ' . $e->getMessage());
                        }
                    }
                }
            }

            // Actualizar image_url principal con la primera imagen
            $firstImage = PropertyImage::where('property_id', $property->id)
                ->orderBy('order')
                ->first();

            if ($firstImage) {
                $validated['image_url'] = $firstImage->image_url;
            }

            // Remover campos no actualizables
            unset($validated['images'], $validated['delete_images'], $validated['reorder_images']);

            // Actualizar propiedad
            $property->update($validated);

            DB::commit();

            // Cargar relaciones
            $property->load([
                'user:id,name,email,phone,photo',
                'images' => function ($q) {
                    $q->orderBy('order');
                }
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Propiedad actualizada correctamente',
                'property' => $property
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando propiedad: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la propiedad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar propiedad (CASCADE automático elimina imágenes)
     */
    public function destroy(Property $property)
    {
        $user = auth()->user();
        $isOwner = $property->user_id === $user->id;
        $isAdmin = in_array($user->role, ['admin', 'support']);

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta propiedad'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Las imágenes se eliminan automáticamente por CASCADE
            $imageCount = $property->images()->count();
            $property->delete();

            DB::commit();

            Log::info("🗑️ Propiedad {$property->id} eliminada con {$imageCount} imágenes");

            return response()->json([
                'success' => true,
                'message' => 'Propiedad eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error eliminando propiedad: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la propiedad'
            ], 500);
        }
    }

    /**
     * Guardar/actualizar ubicación
     */
    public function savePoint(Request $request, $id)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        $property = Property::findOrFail($id);

        $user = auth()->user();
        $isOwner = $property->user_id === $user->id;
        $isAdmin = in_array($user->role, ['admin', 'support']);

        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar la ubicación'
            ], 403);
        }

        $property->update([
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'accuracy' => $validated['accuracy'] ?? null,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Ubicación guardada correctamente',
            'property' => $property
        ]);
    }

    /**
     * Incrementar vistas
     */
    public function incrementViews($id)
    {
        $property = Property::findOrFail($id);
        $property->incrementViews();

        return response()->json([
            'success' => true,
            'message' => 'Visita registrada',
            'views' => $property->views
        ]);
    }

    /**
     * Contar propiedades
     */
    public function count()
    {
        return response()->json([
            'count' => Property::count()
        ]);
    }
}
