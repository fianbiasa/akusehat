<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Progress\Concerns\ResolvesTargetUser;
use App\Models\ProgressPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stored on the 'local' disk (storage/app/private, never web-accessible
 * directly) regardless of is_private - "private" here means "not shared
 * to Coach", not "not stored securely"; every photo is served only
 * through a short-lived signed URL either way (docs/04-Architecture.md
 * §8 "stored outside public web root or behind signed URLs").
 */
class ProgressPhotoController extends Controller
{
    use ResolvesTargetUser;

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);
        $isOwner = $user->id === $request->user()->id;

        $query = $user->progressPhotos()->orderByDesc('logged_at');

        if (! $isOwner) {
            $query->where('is_private', false);
        }

        $photos = $query->get()->map(fn (ProgressPhoto $photo) => [
            'id' => $photo->id,
            'logged_at' => $photo->logged_at->toDateString(),
            'angle' => $photo->angle,
            'is_private' => $photo->is_private,
            'url' => URL::temporarySignedRoute('progress.photos.show', now()->addMinutes(30), ['photo' => $photo->id]),
        ]);

        return response()->json($photos);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'logged_at' => ['nullable', 'date'],
            'angle' => ['required', Rule::in(['front', 'side', 'back'])],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('photo')->store('progress-photos/'.$request->user()->id, 'local');

        $request->user()->progressPhotos()->create([
            'logged_at' => $validated['logged_at'] ?? Carbon::today()->toDateString(),
            'angle' => $validated['angle'],
            'photo_path' => $path,
            'is_private' => true,
            'created_at' => now(),
        ]);

        return back();
    }

    public function update(Request $request, ProgressPhoto $photo): RedirectResponse
    {
        abort_unless($photo->user_id === $request->user()->id, 403);

        $validated = $request->validate(['is_private' => ['required', 'boolean']]);

        $photo->update($validated);

        return back();
    }

    public function destroy(Request $request, ProgressPhoto $photo): RedirectResponse
    {
        abort_unless($photo->user_id === $request->user()->id, 403);

        Storage::disk('local')->delete($photo->photo_path);
        $photo->delete();

        return back();
    }

    /**
     * The signature is the access token here - who was allowed to see
     * this photo was already decided when index() generated the URL
     * (owner sees all their own, everyone else only sees is_private=false
     * ones), so this only needs to verify the signature hasn't expired
     * or been tampered with.
     */
    public function show(Request $request, ProgressPhoto $photo): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        return Storage::disk('local')->response($photo->photo_path);
    }
}
