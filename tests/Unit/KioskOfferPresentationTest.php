<?php

namespace Tests\Unit;

use App\Models\TicketType;
use App\Models\UnitTicketType;
use App\Support\KioskOfferPresentation;
use PHPUnit\Framework\TestCase;

class KioskOfferPresentationTest extends TestCase
{
    public function test_variants_and_descriptions_are_inferred_from_labels(): void
    {
        $this->assertSame(KioskOfferPresentation::VARIANT_PRIORITY, KioskOfferPresentation::variant($this->offer('Atendimento Preferencial', 'Preferencial', 'P')));
        $this->assertSame(KioskOfferPresentation::VARIANT_URGENT, KioskOfferPresentation::variant($this->offer('Emergência', 'Emergencial', 'E')));
        $this->assertSame(KioskOfferPresentation::VARIANT_EXAM, KioskOfferPresentation::variant($this->offer('Exames', 'Exame', 'X')));
        $this->assertSame(KioskOfferPresentation::VARIANT_RETURN, KioskOfferPresentation::variant($this->offer('Retorno', 'Retorno', 'R')));
        $this->assertSame(KioskOfferPresentation::VARIANT_STANDARD, KioskOfferPresentation::variant($this->offer('Atendimento Normal', 'Normal', 'N')));

        $this->assertStringContainsString('prioridades', KioskOfferPresentation::description($this->offer('Preferencial', 'Preferencial', 'P')));
        $this->assertStringContainsString('gerais', KioskOfferPresentation::description($this->offer('Normal', 'Normal', 'N')));
        $this->assertTrue(KioskOfferPresentation::isEmphasized($this->offer('Preferencial', 'Preferencial', 'P')));
        $this->assertFalse(KioskOfferPresentation::isEmphasized($this->offer('Normal', 'Normal', 'N')));
    }

    private function offer(string $displayName, string $typeName, string $prefix): UnitTicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'name' => $typeName,
            'prefix' => $prefix,
        ]);

        $offer = new UnitTicketType;
        $offer->forceFill([
            'display_name' => $displayName,
        ]);
        $offer->setRelation('ticketType', $type);

        return $offer;
    }
}
