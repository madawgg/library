<?php

namespace Tests\Feature\Books;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Spec 002 - RF-03 (cover): conversion to WebP, max 1200 px, max 2 MB with confirmation, removal.
 */
class BookCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * A JPEG full of random pixels: it compresses badly, so it can exceed small size limits.
     */
    private function noisyJpeg(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 2) {
            for ($y = 0; $y < $height; $y += 2) {
                imagefilledrectangle($image, $x, $y, $x + 1, $y + 1, random_int(0, 0xFFFFFF));
            }
        }

        ob_start();
        imagejpeg($image, null, 95);

        return UploadedFile::fake()->createWithContent('noisy.jpg', (string) ob_get_clean());
    }

    /**
     * @return array{0: int, 1: int, mime: string}
     */
    private function storedImageInfo(Book $book): array
    {
        return getimagesizefromstring(Storage::disk('local')->get($book->fresh()->cover_path));
    }

    public function test_large_image_is_stored_as_webp_of_at_most_1200_px_and_2_mb(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('books.form')
            ->set('title', 'Con portada')
            ->set('cover', UploadedFile::fake()->image('portada.jpg', 2400, 1600))
            ->call('save')
            ->assertHasNoErrors();

        $book = Book::where('title', 'Con portada')->firstOrFail();
        $info = $this->storedImageInfo($book);

        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame([1200, 800], [$info[0], $info[1]]);
        $this->assertLessThanOrEqual(2 * 1024 * 1024, Storage::disk('local')->size($book->cover_path));
    }

    public function test_small_image_is_converted_without_being_enlarged(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('books.form')
            ->set('title', 'Pequeña')
            ->set('cover', UploadedFile::fake()->image('portada.png', 600, 900))
            ->call('save')
            ->assertHasNoErrors();

        $info = $this->storedImageInfo(Book::where('title', 'Pequeña')->firstOrFail());

        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame([600, 900], [$info[0], $info[1]]);
    }

    public function test_other_formats_and_files_over_10_mb_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Volt::test('books.form')
            ->set('title', 'Mal formato')
            ->set('cover', UploadedFile::fake()->create('portada.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['cover']);

        Volt::test('books.form')
            ->set('title', 'Muy grande')
            ->set('cover', UploadedFile::fake()->image('portada.jpg')->size(10 * 1024 + 1))
            ->call('save')
            ->assertHasErrors(['cover']);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_cover_picker_offers_camera_and_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/books/create')
            ->assertSee('Hacer una foto')
            ->assertSee('Elegir un archivo')
            ->assertSee('capture="environment"', false);
    }

    public function test_cover_over_the_size_limit_is_compressed_and_needs_confirmation(): void
    {
        config(['books.cover_max_bytes' => 400 * 1024]);
        $book = Book::factory()->create();
        $this->actingAs($book->user);

        $component = Volt::test('books.form', ['book' => $book])
            ->set('cover', $this->noisyJpeg(1000, 1000))
            ->assertSet('coverNeedsConfirmation', true)
            ->assertSet('coverPreviewUrl', fn ($url) => str_starts_with((string) $url, 'data:image/webp;base64,'));

        // Saving without confirming keeps the previous cover.
        $component->call('save')->assertHasErrors(['cover']);
        $this->assertNull($book->fresh()->cover_path);

        $component->call('confirmCompressedCover')->call('save')->assertHasNoErrors();

        $this->assertNotNull($book->fresh()->cover_path);
        $this->assertLessThanOrEqual(400 * 1024, Storage::disk('local')->size($book->fresh()->cover_path));
    }

    public function test_rejecting_the_compressed_cover_leaves_the_cover_unchanged(): void
    {
        config(['books.cover_max_bytes' => 400 * 1024]);
        Storage::disk('local')->put('covers/previous.webp', 'previous');
        $book = Book::factory()->create();
        $book->forceFill(['cover_path' => 'covers/previous.webp'])->save();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('cover', $this->noisyJpeg(1000, 1000))
            ->call('discardCover')
            ->assertSet('coverNeedsConfirmation', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('covers/previous.webp', $book->fresh()->cover_path);
        Storage::disk('local')->assertExists('covers/previous.webp');
    }

    public function test_replacing_the_cover_deletes_the_old_file(): void
    {
        Storage::disk('local')->put('covers/old.webp', 'old');
        $book = Book::factory()->create();
        $book->forceFill(['cover_path' => 'covers/old.webp'])->save();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('cover', UploadedFile::fake()->image('nueva.jpg', 300, 400))
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('local')->assertMissing('covers/old.webp');
        Storage::disk('local')->assertExists($book->fresh()->cover_path);
    }

    public function test_cover_can_be_removed_without_replacement(): void
    {
        Storage::disk('local')->put('covers/old.webp', 'old');
        $book = Book::factory()->create();
        $book->forceFill(['cover_path' => 'covers/old.webp'])->save();
        $this->actingAs($book->user);

        Volt::test('books.form', ['book' => $book])
            ->set('removeCover', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($book->fresh()->cover_path);
        Storage::disk('local')->assertMissing('covers/old.webp');
    }

    public function test_deleting_a_book_deletes_its_cover(): void
    {
        Storage::disk('local')->put('covers/old.webp', 'old');
        $book = Book::factory()->create();
        $book->forceFill(['cover_path' => 'covers/old.webp'])->save();
        $this->actingAs($book->user);

        Volt::test('books.show', ['book' => $book])->call('delete');

        Storage::disk('local')->assertMissing('covers/old.webp');
    }

    public function test_deleting_an_account_deletes_the_covers_of_its_books(): void
    {
        $user = User::factory()->create();
        Storage::disk('local')->put('covers/a.webp', 'a');
        Book::factory()->for($user)->create()->forceFill(['cover_path' => 'covers/a.webp'])->save();
        $this->actingAs($user);

        Volt::test('settings.delete-user-form')->set('password', 'password')->call('deleteUser');

        Storage::disk('local')->assertMissing('covers/a.webp');
    }

    public function test_cover_is_only_served_to_people_who_can_see_the_book(): void
    {
        Storage::disk('local')->put('covers/private.webp', 'webp-bytes');
        $book = Book::factory()->create();
        $book->forceFill(['cover_path' => 'covers/private.webp'])->save();

        $this->actingAs($book->user)->get(route('books.cover', $book))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs(User::factory()->admin()->create())->get(route('books.cover', $book))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('books.cover', $book))->assertForbidden();
    }

    public function test_book_without_cover_has_no_cover_route_response(): void
    {
        $book = Book::factory()->create();

        $this->actingAs($book->user)->get(route('books.cover', $book))->assertNotFound();
    }
}
