<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ActorType;

/**
 * Maps a media collection to the kind of person who is expected to have
 * uploaded into it, so seeded and legacy (unstamped) documents attribute to a
 * human rather than falling through to System.
 *
 * The ERP intake is people-driven: buyers attach their own intent files and
 * signed POs, suppliers attach against the shared attachments collection, and
 * everything internal (goods receiving, completion/payment paperwork, approval
 * documents, supplier quote captures, payment proofs) is handled by staff. A
 * collection with no explicit mapping is treated as staff — a person — never
 * System, because System is reserved for genuine automation.
 *
 * `attachments` is genuinely shared between buyer and supplier portals and
 * cannot be disambiguated from the collection name alone; it is mapped to the
 * buyer here (buyer intent is the dominant origin). Forward uploads keep the
 * precise actor via {@see \App\Actions\Media\AttachUploadedFiles}; this map
 * only backfills provenance that was never stamped.
 */
final readonly class UploaderProvenance
{
    /**
     * Collection name => the actor most likely to have uploaded it.
     *
     * @var array<string, ActorType>
     */
    private const array ACTOR_BY_COLLECTION = [
        // Buyer-facing intake.
        'attachments' => ActorType::Buyer,
        'buyer_po' => ActorType::Buyer,

        // Internal / staff paperwork.
        'quotation' => ActorType::Staff,
        'goods_receive' => ActorType::Staff,
        'completion_reports' => ActorType::Staff,
        'documents' => ActorType::Staff,
        'payment_proof' => ActorType::Staff,
        'proof' => ActorType::Staff,
        'shipping_doc' => ActorType::Staff,
        'pod' => ActorType::Staff,
        'invoice_document' => ActorType::Staff,
    ];

    /**
     * The request-document collections whose provenance this map governs.
     * Used to scope the attribution backfill so branding assets (logos,
     * thumbnails, product images) are never touched.
     *
     * @return list<string>
     */
    public static function documentCollections(): array
    {
        return array_keys(self::ACTOR_BY_COLLECTION);
    }

    /**
     * The actor type expected for uploads into the given collection, defaulting
     * to Staff (a person) for any collection without an explicit mapping —
     * ambiguity resolves to a human, never System.
     */
    public static function actorTypeFor(string $collection): ActorType
    {
        return self::ACTOR_BY_COLLECTION[$collection] ?? ActorType::Staff;
    }
}
