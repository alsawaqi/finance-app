<?php

namespace Tests\Feature;

use Tests\TestCase;

class FinanceRequestOutboundEmailViewTest extends TestCase
{
    public function test_outbound_request_email_renders_composed_body_without_default_greeting_or_signature(): void
    {
        $html = view('emails.finance-request-outbound', [
            'bodyHtml' => '<p>Template message only.</p>',
            'senderName' => 'Staff Sender',
            'senderEmail' => 'staff@example.com',
            'agentName' => 'Agent Receiver',
            'requestReference' => 'REQ-123',
        ])->render();

        $this->assertStringContainsString('Template message only.', $html);
        $this->assertStringNotContainsString('Dear Agent Receiver', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('Staff Sender', $html);
        $this->assertStringNotContainsString('staff@example.com', $html);
    }
}
