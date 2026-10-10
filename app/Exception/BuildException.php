<?php

declare(strict_types=1);

namespace Serafim\Doom\Exception;

final class BuildException extends DoomException
{
    public static function becauseOutputIsUnsafe(string $output, string $allowed): self
    {
        return new self(\sprintf('Refusing to touch "%s": it must be a directory inside "%s"', $output, $allowed));
    }

    public static function becauseSourceIsMissing(string $pathname): self
    {
        return new self(\sprintf('The build input "%s" does not exist', $pathname));
    }

    public static function becauseStepFailed(string $step, string $details): self
    {
        return new self(\sprintf('The build step "%s" failed: %s', $step, $details));
    }

    public static function becauseVerificationFailed(string $problem): self
    {
        return new self(\sprintf('The built folder is not valid: %s', $problem));
    }
}
