<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User\Gender;
use App\Models\User\AgeRange;
use App\Models\User\SocioProfessionalCategory;
use App\Models\User\EducationLevel;
use App\Models\User\EmploymentStatus;
use App\Models\User\MaritalStatus;

class UserProfile extends Model
{
    use HasFactory, SoftDeletes;

    /** Champs requis pour considérer le profil "complet" (voir isComplete()). */
    public const REQUIRED_FOR_COMPLETION = [
        'gender_id',
        'age_range_id',
        'socio_professional_category_id',
        'region',
        'marital_status_id',
    ];

    /** @var array<string> */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'avatar',
        'phone',
        'age',
        'gender_id',
        'birth_year',
        'age_range_id',
        'country',
        'region',
        'city',
        'zip_code',
        'socio_professional_category_id',
        'education_level_id',
        'employment_status_id',
        'marital_status_id',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'age' => 'integer',
        'birth_year' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id');
    }

    public function socioProfessionalCategory(): BelongsTo
    {
        return $this->belongsTo(SocioProfessionalCategory::class, 'socio_professional_category_id');
    }

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class, 'education_level_id');
    }

    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(EmploymentStatus::class, 'employment_status_id');
    }

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class, 'marital_status_id');
    }

    /** Profil "complet" = tous les champs utilisés pour la segmentation démographique des sondages sont renseignés. */
    public function isComplete(): bool
    {
        foreach (self::REQUIRED_FOR_COMPLETION as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
