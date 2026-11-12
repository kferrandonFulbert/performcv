<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Certifications, CvShares, Experiences, Profiles, Users};
use App\Security\ProfileVoter;
use App\Service\{Chronology, ShareAccess};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class DomainRulesTest extends TestCase
{
    public function testChronologyRejectsReversedDates(): void
    {
        $entry = (new Experiences())->setStartDate(new \DateTime('2025-01-01'))->setEndDate(new \DateTime('2024-01-01'));
        self::assertNotNull((new Chronology())->error($entry));
        $entry->setEndDate(new \DateTime('2026-01-01'));
        self::assertNull((new Chronology())->error($entry));
    }

    public function testCurrentPositionCannotHaveEndDate(): void
    {
        $entry = (new Experiences())->setIsCurrent(1)->setEndDate(new \DateTime());
        self::assertNotNull((new Chronology())->error($entry));
        $entry->setEndDate(null);
        self::assertNull((new Chronology())->error($entry));
    }

    public function testCertificationExpiryIsValidated(): void
    {
        $entry = (new Certifications())->setIssueDate(new \DateTime('2026-01-01'))->setExpirationDate(new \DateTime('2025-01-01'));
        self::assertNotNull((new Chronology())->error($entry));
    }

    public function testExpiredDisabledAndNullSharesAreUnavailable(): void
    {
        $policy = new ShareAccess();
        $share = (new CvShares())->setIsPublic(1)->setExpiresAt(new \DateTime('2026-01-01'));
        self::assertFalse($policy->active($share, new \DateTime('2026-01-01')));
        self::assertTrue($policy->active($share, new \DateTime('2025-12-31')));
        $share->setExpiresAt(null)->setIsPublic(0);
        self::assertFalse($policy->active($share));
        $share->setIsPublic(null);
        self::assertFalse($policy->active($share));
    }

    public function testPasswordSessionChangesWithPassword(): void
    {
        $policy = new ShareAccess();
        $share = (new CvShares())->setShareToken('token')->setPasswordHash('first')->setPasswordProtected(1);
        $key = $policy->sessionKey($share);
        self::assertTrue($policy->needsPassword($share));
        $share->setPasswordHash('second');
        self::assertNotSame($key, $policy->sessionKey($share));
    }

    public function testProfileVoterRejectsOtherOwners(): void
    {
        $user = (new Users())->setEmail('owner@example.test');
        $property = new \ReflectionProperty(Users::class, 'id');
        $property->setValue($user, 42);
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $other = new Users();
        $property->setValue($other, 43);
        $voter = new ProfileVoter();
        self::assertSame(1, $voter->vote($token, (new Profiles())->setUser($user), ['PROFILE_EDIT']));
        self::assertSame(-1, $voter->vote($token, (new Profiles())->setUser($other), ['PROFILE_EDIT']));
    }

    public function testSessionSerializationDoesNotContainPasswordHash(): void
    {
        $user = (new Users())->setEmail('owner@example.test')->setPasswordHash('sensitive-password-hash');
        $serialized = serialize($user);
        self::assertStringNotContainsString('sensitive-password-hash', $serialized);
        $restored = unserialize($serialized);
        self::assertSame('owner@example.test', $restored->getUserIdentifier());
        self::assertSame(hash('crc32c', 'sensitive-password-hash'), $restored->getPassword());
    }
}
