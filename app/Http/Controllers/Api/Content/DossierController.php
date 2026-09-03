<?php

namespace App\Http\Controllers\Api\Content;

use App\Http\Controllers\Controller;
use App\Models\Content\Dossier;
use Illuminate\Http\JsonResponse;

class DossierController extends Controller
{
    /**
     * Catalogue des dossiers éditoriaux actifs (sélecteur de la modale de
     * publication + onglet Propriétés).
     */
    public function index(): JsonResponse
    {
        $dossiers = Dossier::active()
            ->with('contentCategories:id,slug')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'description' => $d->description,
                'image_url' => $d->image_url,
                'category_slugs' => $d->contentCategories->pluck('slug')->values(),
            ]);

        return response()->json(['success' => true, 'data' => $dossiers]);
    }

    /**
     * Dossiers épinglés affichés en badges dans la barre de navigation du header.
     * Endpoint public (le header est rendu côté serveur sur les pages publiques).
     */
    public function pinned(): JsonResponse
    {
        $dossiers = Dossier::active()
            ->pinned()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Dossier $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
            ]);

        return response()->json(['success' => true, 'data' => $dossiers]);
    }
}
