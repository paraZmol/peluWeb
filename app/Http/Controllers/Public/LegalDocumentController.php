<?php

namespace App\Http\Controllers\Public;

use App\Data\Legal\PublishedDocument;
use App\Enums\LegalDocumentType;
use App\Http\Controllers\Controller;
use App\Support\Legal\LegalLibrary;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform's legal texts, each with its version, date and the earlier
 * versions still readable.
 */
class LegalDocumentController extends Controller
{
    public function show(LegalDocumentType $document, LegalLibrary $library): Response
    {
        return $this->render($library->current($document), $library);
    }

    public function version(LegalDocumentType $document, string $version, LegalLibrary $library): Response
    {
        return $this->render($library->version($document, $version) ?? abort(404), $library);
    }

    private function render(PublishedDocument $document, LegalLibrary $library): Response
    {
        return Inertia::render('public/legal', [
            'document' => [
                'type' => $document->type->value,
                'title' => $document->title,
                'version' => $document->version,
                'effectiveOn' => $document->effectiveOn->toDateString(),
                'hash' => $document->hash(),
                'isCurrent' => $library->versions($document->type)[0] === $document->version,
                // Our own text from the repository: raw HTML in it is dropped anyway.
                'html' => Str::markdown($document->markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            ],
            'versions' => $library->versions($document->type),
            'others' => collect(LegalDocumentType::cases())
                ->reject(fn (LegalDocumentType $type) => $type === $document->type)
                ->map(fn (LegalDocumentType $type) => ['type' => $type->value, 'label' => $type->label()])
                ->values(),
        ]);
    }
}
