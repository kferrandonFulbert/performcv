<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\{AuditContext, SecurityAudit};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

final class SecurityAuditTest extends TestCase
{
    public function testLoggingFailureDoesNotChangeSecurityFlowOrExposeException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->willThrowException(new \RuntimeException('secret-fixture-must-not-leak'));
        $file = tempnam(sys_get_temp_dir(), 'audit-fallback-');
        $previous = ini_get('error_log');
        try {
            ini_set('error_log', $file);
            $audit = new SecurityAudit($logger, new AuditContext(new RequestStack(), new TokenStorage()));
            $audit->record('auth.logout', 42);
            $fallback = file_get_contents($file);
            self::assertStringContainsString('security audit logging unavailable', $fallback);
            self::assertStringNotContainsString('secret-fixture-must-not-leak', $fallback);
        } finally {
            ini_set('error_log', $previous);
            unlink($file);
        }
    }

    public function testCorrelationIsServerGeneratedAndScopedToRequest(): void
    {
        $requests = new RequestStack();
        $request = Request::create('/', server: ['HTTP_X_REQUEST_ID' => 'client-controlled']);
        $requests->push($request);
        $context = new AuditContext($requests, new TokenStorage());
        $first = $context->requestId();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $first);
        self::assertSame($first, $context->requestId());
        $requests->pop();
        $requests->push(Request::create('/'));
        self::assertNotSame($first, $context->requestId());
    }
}
