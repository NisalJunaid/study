<?php

namespace Tests\Unit;

use App\Support\Overlay\OverlayPayloadFactory;
use Tests\TestCase;

class OverlayPayloadFactoryTest extends TestCase
{
    public function test_invalid_blank_payload_is_not_renderable(): void
    {
        $this->assertNull(OverlayPayloadFactory::renderableOrNull([]));
    }

    public function test_blocking_payload_requires_action_path(): void
    {
        $payload = OverlayPayloadFactory::normalize([
            'title' => 'Blocked',
            'message' => 'Cannot continue.',
            'blocking' => true,
            'dismissible' => false,
        ]);

        $this->assertFalse(OverlayPayloadFactory::isRenderable($payload));
    }

    public function test_redirect_payload_is_actionable_and_renderable(): void
    {
        $payload = OverlayPayloadFactory::redirect(
            title: 'Payment required',
            message: 'Go to payment.',
            redirectUrl: '/billing/payment',
        );

        $this->assertTrue(OverlayPayloadFactory::isRenderable($payload));
        $this->assertSame('/billing/payment', $payload['redirect_url']);
        $this->assertSame('Continue', $payload['primary_label']);
    }
}
