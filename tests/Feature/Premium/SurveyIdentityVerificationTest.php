<?php

namespace Tests\Feature\Premium;

use App\Models\Offer;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La vérification d'identité des votants (`requires_identity_verification`) dépend
 * du champ `allows_identity_verification` de l'offre (admin-configurable, plus de
 * valeur codée en dur) — mais seulement à l'ACTIVATION (transition false→true) : un
 * sondage déjà configuré ainsi reste publiable/modifiable même si l'auteur perd son
 * offre ensuite (grandfathering, même principe que les blocs premium).
 */
class SurveyIdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Offer::create([
            'key' => 'freemium', 'name' => 'Freemium', 'price_cents' => 0, 'period' => 'mois',
            'cta_label' => 'Continuer', 'position' => 1, 'allows_identity_verification' => false,
        ]);
        Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 2, 'allows_identity_verification' => true,
        ]);
    }

    public function test_admin_can_grant_identity_verification_to_the_free_offer(): void
    {
        Offer::where('key', 'freemium')->update(['allows_identity_verification' => true]);
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Sondage sensible',
                'type' => 'survey',
                'requires_identity_verification' => true,
            ])
            ->assertStatus(201);
    }

    public function test_freemium_user_cannot_create_a_survey_requiring_identity_verification(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Sondage sensible',
                'type' => 'survey',
                'requires_identity_verification' => true,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('studio_contents', ['title' => 'Sondage sensible']);
    }

    public function test_premium_user_can_create_a_survey_requiring_identity_verification(): void
    {
        $user = User::factory()->premium()->create();

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson('/api/studio/content', [
                'title' => 'Sondage sensible',
                'type' => 'survey',
                'requires_identity_verification' => true,
            ])
            ->assertStatus(201);
    }

    public function test_freemium_user_cannot_enable_identity_verification_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'type' => 'survey',
            'requires_identity_verification' => false,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", ['requires_identity_verification' => true])
            ->assertStatus(403);

        $this->assertFalse($content->fresh()->requires_identity_verification);
    }

    public function test_freemium_user_can_resave_an_unchanged_identity_verification_flag_via_update(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'type' => 'survey',
            'requires_identity_verification' => true,
        ]);

        // Déjà activé (ex. réglé pendant que l'auteur était Premium) : le renvoyer inchangé
        // dans le payload (autosave classique) n'est jamais bloquant.
        $this->withToken($user->createToken('t')->plainTextToken)
            ->putJson("/api/studio/content/{$content->slug}", [
                'requires_identity_verification' => true,
                'title' => 'Nouveau titre',
            ])
            ->assertStatus(200);
    }

    public function test_freemium_user_can_publish_a_survey_that_already_required_identity_verification(): void
    {
        $user = User::factory()->create();
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'type' => 'survey',
            'requires_identity_verification' => true,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertStatus(200);

        $this->assertSame('published', $content->fresh()->status);
    }

    public function test_premium_user_can_publish_a_survey_requiring_identity_verification(): void
    {
        $user = User::factory()->premium()->create();
        $content = StudioContentFactory::new()->create([
            'user_id' => $user->id,
            'type' => 'survey',
            'requires_identity_verification' => true,
        ]);

        $this->withToken($user->createToken('t')->plainTextToken)
            ->postJson("/api/studio/content/{$content->slug}/publish")
            ->assertStatus(200);
    }
}
