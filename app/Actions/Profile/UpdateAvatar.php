<?php

namespace App\Actions\Profile;

use App\Models\User;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

class UpdateAvatar
{
    public const int SIZE = 512;

    public function __construct(private readonly ImageStore $images) {}

    public function handle(User $user, UploadedFile $file): void
    {
        $previous = $user->avatar_path;

        $user->avatar_path = $this->images->storeSquare($file, "avatars/{$user->id}", self::SIZE);
        $user->save();

        $this->images->delete($previous);
    }

    public function remove(User $user): void
    {
        $this->images->delete($user->avatar_path);

        $user->avatar_path = null;
        $user->save();
    }
}
