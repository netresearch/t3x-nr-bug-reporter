<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Tests\Unit\Decision;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Decision\ReportPolicy;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;
use PHPUnit\Framework\TestCase;

/**
 * The gate that decides whether to OFFER a one-click report. These branches are safety-critical:
 * a wrong "offer=true" is exactly the harmful one-click report the design exists to prevent.
 */
final class ReportPolicyTest extends TestCase
{
    private function github(): TrackerEndpoint
    {
        return new TrackerEndpoint('https://github.com/acme/repo/issues/new', 'github.com', 'support.issues', 'x');
    }

    private function attribution(string $confidence, ?string $culprit = 'acme/repo'): AttributionResult
    {
        return new AttributionResult($culprit, $confidence, [], 'reason');
    }

    public function testWithheldWhenTrackerNotActionable(): void
    {
        $decision = (new ReportPolicy())->decide(
            $this->attribution('high'),
            new TrackerEndpoint(null, 'jira.example.com', 'rejected', 'x'),
            5,
        );
        self::assertFalse($decision['offer']);
    }

    public function testWithheldForLowCoreOrNoneConfidence(): void
    {
        $policy = new ReportPolicy();
        self::assertFalse($policy->decide($this->attribution('low'), $this->github(), 5)['offer']);
        self::assertFalse($policy->decide($this->attribution('core', AttributionResult::CORE), $this->github(), 5)['offer']);
        self::assertFalse($policy->decide($this->attribution('none', null), $this->github(), 5)['offer']);
    }

    public function testWithheldForTooShortTrace(): void
    {
        self::assertFalse((new ReportPolicy())->decide($this->attribution('high'), $this->github(), 2)['offer']);
    }

    public function testWithheldForConfigErrorExceptionClass(): void
    {
        self::assertFalse(
            (new ReportPolicy())->decide($this->attribution('high'), $this->github(), 5, 'Acme\\Exception\\MissingConfigurationException', 'nope')['offer'],
        );
    }

    public function testWithheldForAuthorGuidanceMessage(): void
    {
        self::assertFalse(
            (new ReportPolicy())->decide($this->attribution('high'), $this->github(), 5, 'RuntimeException', 'Please set a component in your controller')['offer'],
        );
    }

    public function testOffersForHighConfidenceRealBug(): void
    {
        $decision = (new ReportPolicy())->decide($this->attribution('high'), $this->github(), 6, 'TypeError', 'Argument #1 must be of type int, string given');
        self::assertTrue($decision['offer']);
    }

    public function testOffersForMediumConfidenceLibrary(): void
    {
        $decision = (new ReportPolicy())->decide($this->attribution('medium', 'acme/lib'), $this->github(), 4, 'LogicException', 'unexpected state');
        self::assertTrue($decision['offer']);
    }
}
