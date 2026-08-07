<?php

declare(strict_types=1);

namespace Netresearch\NrBugReporter\Decision;

use Netresearch\NrBugReporter\Attribution\AttributionResult;
use Netresearch\NrBugReporter\Resolver\TrackerEndpoint;

/**
 * Decides whether to actually OFFER a one-click bug report. Attribution + resolution can produce an
 * actionable GitHub URL, but filing it may still be wrong: low-confidence guesses, TYPO3 core, too-
 * short traces, and integrator/config/author errors should never become one-click upstream reports.
 *
 * This is the gate that keeps the (now robust) resolver from amplifying attribution mistakes into
 * harmful public issues — actionable resolution raised the stakes, so acting requires confidence.
 */
final class ReportPolicy
{
    /** Exception class names that signal a configuration problem, not an upstream code defect. */
    private const CONFIG_ERROR_CLASS = '~(Configuration|MissingConfig|NotConfigured)~i';

    /** Exception messages that are integrator/author guidance ("set X", "not configured", ...). */
    private const AUTHOR_MESSAGE = '~(please set|no configuration found|not configured|must be (set|configured|provided|defined)|is not set|not set|missing .{0,40}config|set (a|the) .{0,40}in your controller)~i';

    /** A trace shorter than this carries too little context to attribute with confidence. */
    private const MIN_FRAMES = 3;

    /**
     * @return array{offer:bool, reason:string}
     */
    public function decide(
        AttributionResult $result,
        TrackerEndpoint $tracker,
        int $frameCount,
        string $exceptionClass = '',
        string $message = '',
    ): array {
        if (!$tracker->isActionable()) {
            return ['offer' => false, 'reason' => "no actionable GitHub tracker ({$tracker->status})"];
        }
        if (in_array($result->confidence, ['low', 'core', 'none'], true)) {
            return ['offer' => false, 'reason' => "confidence '{$result->confidence}' too low to file upstream"];
        }
        if ($frameCount < self::MIN_FRAMES) {
            return ['offer' => false, 'reason' => "trace too short ({$frameCount} frames) to attribute reliably"];
        }
        if (preg_match(self::CONFIG_ERROR_CLASS, $exceptionClass) === 1 || preg_match(self::AUTHOR_MESSAGE, $message) === 1) {
            return ['offer' => false, 'reason' => 'looks like an integrator/config/author error, not an upstream bug'];
        }

        return ['offer' => true, 'reason' => 'offer prefilled report'];
    }
}
