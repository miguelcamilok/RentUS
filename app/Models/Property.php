<?php

namespace App\Models;

use App\Traits\HasSmartScopes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class Property extends Model
{
    use HasFactory, HasSmartScopes;

    // Campos asignables masivamente
    protected $fillable = [
        'title',
        'description',
        'address',
        'city',
        'type',
        'status',
        'approval_status',
        'visibility',
        'monthly_price',
        'area_m2',
        'num_bedrooms',
        'num_bathrooms',
        'included_services',
        'publication_date',
        'image_url',
        'user_id',
        'views',
        'lat',
        'lng',
        'accuracy' // 🔥 AGREGAR accuracy
    ];

    // Cast para JSON
    protected $casts = [
        'included_services' => 'array',
        'publication_date'  => 'date',
        'monthly_price'     => 'decimal:2',
        'area_m2'           => 'decimal:2',
        'lat'               => 'decimal:7',
        'lng'               => 'decimal:7',
        'accuracy'          => 'decimal:2', // 🔥 AGREGAR cast para accuracy
        'views'             => 'integer',
    ];

    // Valores por defecto
    protected $attributes = [
        'status' => 'available',
        'approval_status' => 'pending',
        'visibility' => 'hidden',
        'views' => 0,
    ];

    // ==================== RELACIONES ====================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔥 NUEVA RELACIÓN: Múltiples imágenes
    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('order');
    }

    // 🔥 NUEVA RELACIÓN: Imagen principal
    public function mainImage()
    {
        return $this->hasOne(PropertyImage::class)
                    ->where('is_main', true)
                    ->orderBy('order');
    }

    public function maintenances()
    {
        return $this->hasMany(Maintenance::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function rentalRequests()
    {
        return $this->hasMany(RentalRequest::class);
    }

    // ==================== SCOPES PARA FILTROS ====================

    /**
     * Scope: Búsqueda por texto en múltiples campos
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->orWhere('city', 'LIKE', "%{$search}%")
                ->orWhere('address', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Scope: Filtrar por estado de disponibilidad
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope: Filtrar por estado de aprobación
     */
    public function scopeApprovalStatus(Builder $query, ?string $approvalStatus): Builder
    {
        if (empty($approvalStatus)) {
            return $query;
        }

        return $query->where('approval_status', $approvalStatus);
    }

    /**
     * Scope: Filtrar por visibilidad
     */
    public function scopeVisibility(Builder $query, ?string $visibility): Builder
    {
        if (empty($visibility)) {
            return $query;
        }

        return $query->where('visibility', $visibility);
    }

    /**
     * Scope: Filtrar por ciudad
     */
    public function scopeCity(Builder $query, ?string $city): Builder
    {
        if (empty($city)) {
            return $query;
        }

        return $query->where('city', 'LIKE', "%{$city}%");
    }

    /**
     * Scope: Filtrar por rango de precio mínimo
     */
    public function scopeMinPrice(Builder $query, ?float $minPrice): Builder
    {
        if ($minPrice === null) {
            return $query;
        }

        return $query->where('monthly_price', '>=', $minPrice);
    }

    /**
     * Scope: Filtrar por rango de precio máximo
     */
    public function scopeMaxPrice(Builder $query, ?float $maxPrice): Builder
    {
        if ($maxPrice === null) {
            return $query;
        }

        return $query->where('monthly_price', '<=', $maxPrice);
    }

    /**
     * Scope: Incluir relaciones (eager loading)
     */
    public function scopeIncluded(Builder $query): Builder
    {
        return $query->with([
            'user:id,name,email,phone,photo',
            'images' => function($q) {
                $q->orderBy('order');
            }
        ]);
    }

    // ==================== MÉTODOS AUXILIARES ====================

    /**
     * Incrementar vistas de la propiedad
     */
    public function incrementViews(): void
    {
        $this->increment('views');
    }

    /**
     * Verificar si la propiedad está disponible
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available'
            && $this->approval_status === 'approved'
            && $this->visibility === 'published';
    }

    /**
     * Verificar si la propiedad está aprobada
     */
    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * Verificar si la propiedad está publicada
     */
    public function isPublished(): bool
    {
        return $this->visibility === 'published';
    }
}
