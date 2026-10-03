<?php

namespace Tests\Feature;

use App\Models\QualityDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Uploading a controlled document: any format, and a visible percentage.
 *
 * A controlled document is whatever the company issues - a PDF policy, an Excel
 * register, a CAD drawing, a scanned manual, a training video - so the server
 * refuses no format and caps only the size. The tests below upload deliberately
 * unusual extensions to keep it that way: the moment somebody adds a mimes:
 * rule, these fail.
 *
 * The progress bar itself is browser behaviour and cannot be asserted here, so
 * what is asserted is that the markup it hangs off actually reaches the page -
 * the drop zone, and the script that wires it up. A bar that renders nowhere is
 * the same bug as no bar at all.
 */
class QualityDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        Role::findOrCreate('quality-manager', 'web');
        Role::findOrCreate('employee', 'web');

        $user = User::create([
            'name' => 'Quality Manager',
            'email' => 'qm@test.local',
            'password' => bcrypt('secret'),
        ]);
        $user->assignRole(['employee', 'quality-manager']);

        return $user;
    }

    private function document(User $initiator): QualityDocument
    {
        return QualityDocument::create([
            'doc_number' => QualityDocument::nextNumber(),
            'title' => 'Quality Manual',
            'category' => 'manual',
            'status' => 'draft',
            'initiator_id' => $initiator->id,
        ]);
    }

    // ===== Any format =====

    public static function formats(): array
    {
        return [
            'pdf policy' => ['policy.pdf', 'application/pdf'],
            'word procedure' => ['procedure.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'excel register' => ['register.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'cad drawing' => ['site-plan.dwg', 'application/acad'],
            'training video' => ['induction.mp4', 'video/mp4'],
            'scanned manual' => ['manual.tiff', 'image/tiff'],
            'zip bundle' => ['forms.zip', 'application/zip'],
            'no extension at all' => ['README', 'application/octet-stream'],
        ];
    }

    #[DataProvider("formats")]
    public function test_any_format_is_accepted(string $name, string $mime): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $doc = $this->document($manager);

        $this->actingAs($manager)
            ->post("/quality/documents/{$doc->id}/upload", [
                'file' => UploadedFile::fake()->create($name, 16, $mime),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $file = $doc->fresh()->currentFile();

        $this->assertNotNull($file, "{$name} was not stored.");
        $this->assertSame($name, $file->original_name);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_a_file_over_the_ceiling_is_refused(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $doc = $this->document($manager);

        $overKb = QualityDocument::maxUploadKb() + 1024;

        $this->actingAs($manager)
            ->post("/quality/documents/{$doc->id}/upload", [
                'file' => UploadedFile::fake()->create('huge.pdf', $overKb, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertNull($doc->fresh()->currentFile(), 'An oversized file was stored anyway.');
    }

    /** The ceiling must never exceed what PHP itself will accept. */
    public function test_the_ceiling_is_clamped_to_what_the_server_allows(): void
    {
        $ceiling = QualityDocument::maxUploadKb();

        $this->assertGreaterThanOrEqual(1024, $ceiling);

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $raw = ini_get($setting);
            if (! $raw || $raw === '-1') {
                continue;
            }
            $bytes = (int) $raw * match (strtolower(substr($raw, -1))) {
                'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1,
            };
            $this->assertLessThanOrEqual(
                (int) ($bytes / 1024),
                $ceiling,
                "The upload ceiling is above {$setting}, so PHP would reject the request before validation ran."
            );
        }
    }

    // ===== The progress UI reaches the page =====

    public function test_the_upload_screens_carry_the_drop_zone_and_its_script(): void
    {
        $manager = $this->manager();
        $doc = $this->document($manager);

        foreach (['/quality/documents/create', "/quality/documents/{$doc->id}"] as $url) {
            $response = $this->actingAs($manager)->get($url)->assertOk();

            $response->assertSee('data-file-drop', false);
            $response->assertSee('Any file format', false);
            $response->assertSee('data-upload-progress', false);
            // The script that turns it into a percentage, from <x-transfer-progress>.
            $response->assertSee('data-pp-bar', false);
        }
    }

    public function test_downloads_are_wired_for_progress(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $doc = $this->document($manager);

        $this->actingAs($manager)->post("/quality/documents/{$doc->id}/upload", [
            'file' => UploadedFile::fake()->create('manual.pdf', 16, 'application/pdf'),
        ]);

        $this->actingAs($manager)
            ->get("/quality/documents/{$doc->id}")
            ->assertOk()
            ->assertSee('data-download-progress', false)
            ->assertSee('data-filename="manual.pdf"', false);
    }

    /** The file itself still comes back. */
    public function test_the_stored_file_downloads(): void
    {
        Storage::fake('local');
        $manager = $this->manager();
        $doc = $this->document($manager);

        $this->actingAs($manager)->post("/quality/documents/{$doc->id}/upload", [
            'file' => UploadedFile::fake()->create('manual.pdf', 16, 'application/pdf'),
        ]);

        $file = $doc->fresh()->currentFile();

        $this->actingAs($manager)
            ->get("/quality/documents/{$doc->id}/files/{$file->id}/download")
            ->assertOk()
            ->assertDownload('manual.pdf');
    }
}
