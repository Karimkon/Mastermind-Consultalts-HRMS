<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProfilePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Staff could not set a profile picture.
 *
 * The rule was `image|max:2048` - two megabytes. A photo from any current phone
 * is three to eight, so it failed validation and all anybody saw was a refusal.
 * One avatar already on the system is 1.5MB, so the ceiling was barely above
 * what was in use.
 *
 * The photo is now accepted large and reduced on the server, so the tests below
 * use real generated images at real phone sizes rather than
 * UploadedFile::fake()->image(), which produces a tiny file that would have
 * passed the old rule too and proved nothing.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $email = 'staff@test.local'): User
    {
        Role::findOrCreate('employee', 'web');
        $u = User::create(['name' => 'Mbogga Julius', 'email' => $email, 'password' => bcrypt('secret')]);
        $u->assignRole('employee');

        return $u;
    }

    /**
     * A real JPEG of the given pixel size, written to a temp file.
     *
     * $padToBytes grows the FILE without growing the picture, by appending
     * random bytes after the JPEG end-of-image marker. Decoders ignore anything
     * past that marker, so the result is still a valid photo that GD opens -
     * but it weighs what a phone photo weighs, which is the thing validation
     * actually measures. Generating twelve megapixels of genuine noise takes
     * fourteen seconds per call; this takes none.
     */
    private function photo(int $w, int $h, int $padToBytes = 0, string $name = 'photo.jpg'): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);

        for ($i = 0; $i < 60; $i++) {
            $colour = imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255));
            imagefilledrectangle(
                $im,
                random_int(0, $w - 1), random_int(0, $h - 1),
                random_int(0, $w - 1), random_int(0, $h - 1),
                $colour
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'pp') . '-' . $name;
        imagejpeg($im, $path, 90);
        imagedestroy($im);

        if ($padToBytes > 0 && filesize($path) < $padToBytes) {
            file_put_contents($path, random_bytes($padToBytes - filesize($path)), FILE_APPEND);
            // PHP caches stat results, so without this both filesize() here and
            // UploadedFile::getSize() keep reporting the size before the append
            // and the padding looks as though it never happened.
            clearstatcache(true, $path);
        }

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    // ===== The bug =====

    /** The size that used to be refused. */
    public function test_a_photo_the_size_of_a_phone_photo_is_accepted(): void
    {
        Storage::fake('public');
        $user = $this->staff();

        $photo = $this->photo(1200, 1600, 3 * 1024 * 1024, 'IMG_2026.jpg');

        // Guard the premise: this has to be bigger than the old 2MB rule, or
        // the test proves nothing.
        $this->assertGreaterThan(
            2048 * 1024,
            $photo->getSize(),
            'The generated photo is not large enough to reproduce the bug.'
        );

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => $photo])
            ->assertOk()
            ->assertJsonStructure(['url']);

        $user->refresh();
        $this->assertNotNull($user->avatar, 'No avatar was saved.');
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_the_stored_avatar_is_small_even_when_the_photo_was_not(): void
    {
        Storage::fake('public');
        $user = $this->staff();

        $photo = $this->photo(1200, 1600, 3 * 1024 * 1024);
        $uploadedBytes = $photo->getSize();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $photo])->assertOk();

        $storedBytes = strlen(Storage::disk('public')->get($user->fresh()->avatar));

        $this->assertLessThan(
            $uploadedBytes / 4,
            $storedBytes,
            'The photo was stored at full size; the disk will fill up.'
        );

        // And it is a square image of the expected side.
        $info = getimagesizefromstring(Storage::disk('public')->get($user->fresh()->avatar));
        $this->assertSame(512, $info[0]);
        $this->assertSame(512, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_a_portrait_photo_is_cropped_square_not_squashed(): void
    {
        Storage::fake('public');
        $user = $this->staff();

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => $this->photo(1200, 2400)])
            ->assertOk();

        $info = getimagesizefromstring(Storage::disk('public')->get($user->fresh()->avatar));
        $this->assertSame($info[0], $info[1], 'A tall photo did not come out square.');
    }

    // ===== Everyone, not just one person =====

    public static function everyRole(): array
    {
        return [
            'ordinary employee' => ['employee'],
            'quality manager' => ['quality-manager'],
            'auditor' => ['auditor'],
            'hr admin' => ['hr-admin'],
            'manager' => ['manager'],
            'account manager' => ['account-manager'],
            'super admin' => ['super-admin'],
        ];
    }

    #[DataProvider('everyRole')]
    public function test_every_member_of_staff_can_set_a_photo(string $role): void
    {
        Storage::fake('public');
        Role::findOrCreate($role, 'web');
        Role::findOrCreate('employee', 'web');

        $u = User::create([
            'name' => 'Test ' . $role,
            'email' => str_replace('-', '', $role) . '@test.local',
            'password' => bcrypt('secret'),
        ]);
        $u->assignRole(['employee', $role]);

        $this->actingAs($u)
            ->post('/profile/avatar', ['avatar' => $this->photo(800, 600)])
            ->assertOk();

        $this->assertNotNull($u->fresh()->avatar, "{$role} could not set a photo.");
    }

    // ===== Housekeeping and limits =====

    public function test_replacing_a_photo_removes_the_old_file(): void
    {
        Storage::fake('public');
        $user = $this->staff();

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $this->photo(400, 400)])->assertOk();
        $first = $user->fresh()->avatar;

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $this->photo(450, 450)])->assertOk();
        $second = $user->fresh()->avatar;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_something_that_is_not_a_picture_is_refused(): void
    {
        Storage::fake('public');
        $user = $this->staff();

        $this->actingAs($user)
            ->post('/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('payroll.xlsx', 40,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar);
    }

    public function test_the_ceiling_is_well_above_a_phone_photo_and_within_what_php_allows(): void
    {
        $max = ProfilePhoto::maxKb();

        $this->assertGreaterThanOrEqual(8192, $max, 'The ceiling is too low for a phone photo.');

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $raw = ini_get($setting);
            if (! $raw || $raw === '-1') {
                continue;
            }
            $bytes = (int) $raw * match (strtolower(substr($raw, -1))) {
                'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
            };
            $this->assertLessThanOrEqual((int) ($bytes / 1024), $max,
                "The ceiling is above {$setting}, so PHP would reject the request before validation ran.");
        }
    }

    // ===== The page offers both ways in =====

    public function test_the_profile_page_offers_the_camera_and_the_device(): void
    {
        $response = $this->actingAs($this->staff())->get('/profile')->assertOk();

        $response->assertSee('Take a photo', false);
        $response->assertSee('Upload from device', false);
        // capture= is what asks the phone for the camera rather than the gallery.
        $response->assertSee('capture="user"', false);
        $response->assertSee('id="avatar-progress"', false);
    }
}
