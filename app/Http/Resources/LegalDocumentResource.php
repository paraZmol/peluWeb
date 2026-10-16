<?php

namespace App\Http\Resources;

use App\Data\Legal\PublishedDocument;
use App\Enums\LegalDocumentType;
use App\Support\Legal\LegalLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A legal text as the acceptance screen lists it: what it is, which
 * version and where to read it.
 *
 * @property PublishedDocument $resource
 */
class LegalDocumentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{type: string, title: string, label: string, version: string, effectiveOn: string, changes: string, url: string}
     */
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'type' => $document->type->value,
            'title' => $document->title,
            'label' => $document->type->label(),
            'version' => $document->version,
            'effectiveOn' => $document->effectiveOn->toDateString(),
            'changes' => $document->changes,
            'url' => route('legal.show', $document->type),
        ];
    }

    /**
     * @param  list<PublishedDocument>|null  $documents  defaults to the current account texts
     * @return list<array<string, string>>
     */
    public static function forAccounts(LegalLibrary $library, ?array $documents = null): array
    {
        $documents ??= array_map($library->current(...), LegalDocumentType::forAccounts());

        return array_map(fn (PublishedDocument $document): array => (new self($document))->resolve(), $documents);
    }
}
