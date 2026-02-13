<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\HasSmartScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasSmartScopes,  HasApiTokens;

    // Campos que se pueden asignar masivamente (por create, update, etc.)
    protected $fillable = [
        'name',                  // Nombre del usuario
        'email',                 // Correo electrónico del usuario
        'password_hash',         // Contraseña del usuario (almacenada como hash)
        'phone',                 // Teléfono del usuario
        'address',               // Dirección del usuario
        'id_documento',          // Documento de identificación del usuario
        'status',                // Estado del usuario (activo, inactivo, etc.)
        'registration_date',     // Fecha de registro del usuario
        'password'               // Contraseña del usuario (sin hash, se usa para autenticación)
    ];

    // Ocultar campos sensibles en respuestas JSON
    protected $hidden = [
        'password',
        'remember_token',
        'password_hash'
    ];

    // Convertir automáticamente ciertos campos a tipos nativos
    protected $casts = [
        'email_verified_at' => 'datetime'
    ];

    public function properties(){return $this->hasMany(Property::class);}

    public function contracts(){return $this->hasMany(Contract::class);}

    public function reports(){return $this->hasMany(Report::class);}

    public function maintenances(){return $this->hasMany(Maintenance::class);}

    public function rentalRequest(){return $this->hasMany(RentalRequest::class);}

    public function ratings(){return $this->hasMany(Rating::class);}

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

        public function getJWTIdentifier()
    {
        return $this->getKey(); // normalmente es el id
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
