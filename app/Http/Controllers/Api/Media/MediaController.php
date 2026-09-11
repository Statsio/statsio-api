<?php

namespace App\Http\Controllers\Api\Media;

use App\Domain\Media\Actions\MediaAction;
use App\Domain\Content\Support\StudioContentAssetOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\UploadMediaRequest;
use App\Http\Requests\Media\UploadMultipleMediaRequest;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(
        private MediaAction $mediaAction
    ) {}

    /** Bibliothèque de médias : utilisateur courant, ou propriétaire du contenu si contexte partagé. */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $slug = $request->query('studio_content_slug');
        if ($slug) {
            $resolved = StudioContentAssetOwner::resolveForWrite($request->user(), (string) $slug);
            $userId = $resolved['user_id'];
        }

        $media = Media::query()
            ->forUser($userId)
            ->images()
            ->latest('id')
            ->limit(300)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $media->map(fn (Media $m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $this->mediaAction->getUrl($m),
                'created_at' => $m->created_at,
            ])->values(),
        ]);
    }

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $directory = $request->input('directory', 'media');

            $media = $this->mediaAction->upload($file, $directory);

            $userId = $request->user()?->id;
            $slug = $request->input('studio_content_slug');
            if ($slug && $request->user()) {
                $resolved = StudioContentAssetOwner::resolveForWrite($request->user(), (string) $slug);
                $userId = $resolved['user_id'];
            }

            if ($userId) {
                $media->forceFill(['user_id' => $userId])->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $media->id,
                    'path' => $media->path,
                    'type' => $media->type,
                    'url' => $this->mediaAction->getUrl($media),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadMultiple(UploadMultipleMediaRequest $request): JsonResponse
    {
        try {
            $files = $request->file('files');
            $directory = $request->input('directory', 'media');

            $mediaItems = $this->mediaAction->uploadMultiple($files, $directory);

            $data = collect($mediaItems)->map(function ($media) {
                return [
                    'id' => $media->id,
                    'path' => $media->path,
                    'type' => $media->type,
                    'url' => $this->mediaAction->getUrl($media),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload des fichiers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        abort_unless($media->user_id === $request->user()->id, 403);

        try {
            $this->mediaAction->delete($media);

            return response()->json([
                'success' => true,
                'message' => 'Média supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du média',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $media->id,
                'path' => $media->path,
                'type' => $media->type,
                'url' => $this->mediaAction->getUrl($media),
                'created_at' => $media->created_at,
                'updated_at' => $media->updated_at,
            ],
        ]);
    }

    public function file(Media $media)
    {
        $disk = $this->mediaAction->disk();

        abort_unless(Storage::disk($disk)->exists($media->path), 404);

        return Storage::disk($disk)->response($media->path);
    }
}
