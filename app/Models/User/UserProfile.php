<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    /** Préférences de notifications e-mail par défaut (voir getNotificationPreferencesAttribute). */
    public const DEFAULT_NOTIFICATION_PREFERENCES = [
        'articles' => true,
        'weekly' => true,
        'replies' => false,
        'offers' => false,
    ];

    /** @var array<string> */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'avatar',
        'notification_preferences',
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

    /**
     * Préférences de notifications e-mail. En lecture : fusionnées avec les valeurs
     * par défaut (profil jamais configuré, ou nouvelle clé ajoutée après coup).
     * En écriture : on ne conserve que les clés connues.
     */
    protected function notificationPreferences(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => array_merge(
                self::DEFAULT_NOTIFICATION_PREFERENCES,
                array_intersect_key(
                    (array) json_decode($value ?? '[]', true),
                    self::DEFAULT_NOTIFICATION_PREFERENCES,
                ),
            ),
            set: fn ($value) => json_encode(array_map(
                fn ($v) => (bool) $v,
                array_intersect_key((array) $value, self::DEFAULT_NOTIFICATION_PREFERENCES),
            )),
        );
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
