<?php

use App\Category;
use App\Channel;
use App\Hashtag;
use App\User;
use App\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inserta videos de demostración (URLs públicas de muestra) solo si no hay
 * publicaciones activas en el feed. Idempotente respecto a ese criterio.
 */
class DemoPostsSeeder extends Seeder
{
    public function run()
    {
        $hasPublished = Video::query()
            ->where('is_published', true)
            ->where('moderation_status', 'active')
            ->exists();

        if ($hasPublished) {
            return;
        }

        $user = User::query()->whereHas('channel')->first();
        if (!$user) {
            $user = User::query()->first();
            if ($user && !$user->channel) {
                Channel::create([
                    'user_id' => $user->id,
                    'slug' => Str::slug(($user->username ?: 'canal') . '-' . $user->id),
                    'display_name' => $user->name,
                ]);
                $user->load('channel');
            }
        }

        if (!$user || !$user->channel) {
            if (isset($this->command)) {
                $this->command->warn('DemoPostsSeeder: no hay usuario con canal. Ejecuta AdminUserSeeder antes o crea un usuario.');
            }

            return;
        }

        $channelId = $user->channel->id;

        $catDemo = Category::firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo']
        );
        $catGeneral = Category::firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'General']
        );

        $posts = [
            [
                'title' => 'Ejemplo: Big Buck Bunny (corto de demostración)',
                'description' => 'Video de muestra alojado en Google Cloud Storage. Puedes borrarlo o sustituirlo publicando contenido propio desde «Publicar».',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'thumbnail_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/images/BigBuckBunny.jpg',
                'duration_seconds' => 596,
                'category_ids' => [$catDemo->id, $catGeneral->id],
                'hashtag_names' => ['demo', 'ejemplo'],
            ],
            [
                'title' => 'Ejemplo: Elephants Dream',
                'description' => 'Segunda publicación de ejemplo con categorías y hashtags para probar filtros del feed.',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'thumbnail_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/images/ElephantsDream.jpg',
                'duration_seconds' => 653,
                'category_ids' => [$catGeneral->id],
                'hashtag_names' => ['demo', 'animacion'],
            ],
            [
                'title' => 'Ejemplo: For Bigger Blazes',
                'description' => 'Clip corto ideal para comprobar la reproducción en tarjetas y en la página del post.',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'thumbnail_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/images/ForBiggerBlazes.jpg',
                'duration_seconds' => 15,
                'category_ids' => [$catDemo->id],
                'hashtag_names' => ['demo', 'corto'],
            ],
        ];

        DB::transaction(function () use ($posts, $user, $channelId) {
            foreach ($posts as $row) {
                $slug = Str::slug(Str::limit($row['title'], 80, '')) . '-' . Str::lower(Str::random(6));

                $video = Video::create([
                    'channel_id' => $channelId,
                    'author_id' => $user->id,
                    'title' => $row['title'],
                    'slug' => $slug,
                    'description' => $row['description'],
                    'video_url' => $row['video_url'],
                    'preview_url' => $row['thumbnail_url'] ?? null,
                    'thumbnail_url' => $row['thumbnail_url'] ?? null,
                    'duration_seconds' => (int) ($row['duration_seconds'] ?? 0),
                    'views_count' => random_int(12, 800),
                    'likes_count' => random_int(0, 120),
                    'dislikes_count' => random_int(0, 15),
                    'is_published' => true,
                    'published_at' => now()->subHours(random_int(1, 120)),
                    'moderation_status' => 'active',
                ]);

                $video->media()->create([
                    'type' => 'video',
                    'url' => $row['video_url'],
                    'position' => 0,
                ]);

                if (!empty($row['category_ids'])) {
                    $video->categories()->sync($row['category_ids']);
                }

                if (!empty($row['hashtag_names'])) {
                    $ids = collect($row['hashtag_names'])
                        ->map(function ($name) {
                            $n = Str::lower(ltrim(trim($name), '#'));

                            return $n === '' ? null : Hashtag::firstOrCreate(['name' => $n])->id;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    if ($ids !== []) {
                        $video->hashtags()->sync($ids);
                    }
                }
            }
        });

        if (isset($this->command)) {
            $this->command->info('DemoPostsSeeder: creadas ' . count($posts) . ' publicaciones de ejemplo.');
        }
    }
}
