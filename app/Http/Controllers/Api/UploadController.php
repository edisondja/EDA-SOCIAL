<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function media(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $file = $data['file'];
        $mime = (string) $file->getMimeType();
        $isVideo = strncmp($mime, 'video/', 6) === 0;
        $isImage = strncmp($mime, 'image/', 6) === 0;

        if (!$isVideo && !$isImage) {
            return response()->json(['message' => 'Tipo de archivo no permitido.'], 422);
        }

        $path = $file->store('uploads', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'url' => $url,
            'type' => $isVideo ? 'video' : 'image',
        ]);
    }
}
