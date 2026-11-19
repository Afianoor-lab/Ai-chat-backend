<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
   public const STATUS_MODES = ['online', 'away', 'offline', 'dnd'];
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
      
        'name',
        'email',
        'password',
        'email_verified_at',
        'ip_address',
        'user_agent',
        'role',
        'password_changed_at',
        'remember_token',
        'last_activity',
        'openrouter_api_key', 
        'default_model', 
        'passcode'
        
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'openrouter_api_key',
         'passcode'
    ];

   

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'openrouter_api_key' => 'encrypted',
            'passcode' => 'hashed',
        
        ];
    }


    
//    public function companies()
// {
//     return $this->belongsToMany(Company::class, null, 'user_ids', 'company_ids');
// }

// public function roles()
// {
//     return $this->hasMany(UserRoles::class, 'user_id');
// }

// public function createdCompany()
//     {
       
//         return $this->hasOne(Company::class, 'creator_id', '_id');
        
       
//     }

public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }


}
