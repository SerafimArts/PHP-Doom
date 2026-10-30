<?php

declare(strict_types=1);

namespace Local\GPU\Window;

use FFI\CData;
use Local\GLFW\Constants\Glfw;
use Local\GLFW\Exception\GlfwException;
use Local\GLFW\GLFW3;
use Local\GPU\Exception\WindowException;

/**
 * A GLFW window without a graphics context, used as a Vulkan surface and as the source of input.
 *
 * The process owns at most one window: GLFW is initialized with the first one and terminated with it.
 */
final class Window
{
    public readonly \FFI $glfw;

    private readonly WindowState $state;

    /** @var list<\Closure> */
    // @phpstan-ignore-next-line the closures only have to stay referenced
    private array $callbacks = [];

    private ?CData $handle;

    private readonly CData $x;

    private readonly CData $y;

    private readonly CData $width;

    private readonly CData $height;

    /**
     * @throws WindowException in case of GLFW or the window cannot be created
     */
    public function __construct(WindowOptions $options = new WindowOptions())
    {
        $this->state = new WindowState();
        $this->glfw = $this->load();

        $this->x = $this->glfw->new('double');
        $this->y = $this->glfw->new('double');
        $this->width = $this->glfw->new('int');
        $this->height = $this->glfw->new('int');

        $state = $this->state;
        $this->callbacks[] = $errorCallback = static function (int $code, string $description) use ($state): void {
            $state->errors[] = \sprintf('GLFW error 0x%X: %s', $code, $description);
        };
        $this->glfw->glfwSetErrorCallback($errorCallback);

        if ($this->glfw->glfwInit() !== Glfw::TRUE) {
            throw WindowException::becauseGlfwNotInitialized();
        }

        $this->glfw->glfwWindowHint(Glfw::CLIENT_API, Glfw::NO_API);
        $this->glfw->glfwWindowHint(Glfw::RESIZABLE, Glfw::fromBool($options->isResizable));

        /** @var CData|null $handle */
        $handle = $this->glfw->glfwCreateWindow($options->width, $options->height, $options->title, null, null);

        if ($handle === null) {
            $details = \implode('; ', $this->state->errors);
            $this->glfw->glfwTerminate();

            throw WindowException::becauseWindowNotCreated($details);
        }

        $this->handle = $handle;

        $this->callbacks[] = $keyCallback = static function (
            CData $window,
            int $key,
            int $scancode,
            int $action,
            int $mods,
        ) use ($state): void {
            $state->keyEvents[] = new KeyEvent($key, $action);
        };
        $this->glfw->glfwSetKeyCallback($handle, $keyCallback);

        $this->callbacks[] = $sizeCallback = static function (
            CData $window,
            int $width,
            int $height,
        ) use ($state): void {
            $state->isResized = true;
        };
        $this->glfw->glfwSetFramebufferSizeCallback($handle, $sizeCallback);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function shouldClose(): bool
    {
        return $this->glfw->glfwWindowShouldClose($this->getHandle()) !== Glfw::FALSE;
    }

    public function requestClose(): void
    {
        $this->glfw->glfwSetWindowShouldClose($this->getHandle(), Glfw::TRUE);
    }

    public function pollEvents(): void
    {
        $this->glfw->glfwPollEvents();
    }

    /**
     * Blocks until an event arrives or the timeout (in seconds) elapses, used while the window is minimized.
     */
    public function waitEvents(float $timeout): void
    {
        $this->glfw->glfwWaitEventsTimeout($timeout);
    }

    /**
     * Size of the framebuffer in pixels, zero while the window is minimized.
     *
     * @return array{int<0, max>, int<0, max>}
     */
    public function getFramebufferSize(): array
    {
        $this->glfw->glfwGetFramebufferSize($this->getHandle(), \FFI::addr($this->width), \FFI::addr($this->height));

        $width = $this->width->cdata;
        $height = $this->height->cdata;
        \assert(\is_int($width) && $width >= 0 && \is_int($height) && $height >= 0);

        return [$width, $height];
    }

    /**
     * Tells whether the framebuffer changed its size since the previous call.
     */
    public function consumeResize(): bool
    {
        $isResized = $this->state->isResized;
        $this->state->isResized = false;

        return $isResized;
    }

    public function isKeyDown(int $key): bool
    {
        return $this->glfw->glfwGetKey($this->getHandle(), $key) === Glfw::PRESS;
    }

    public function isMouseButtonDown(int $button): bool
    {
        return $this->glfw->glfwGetMouseButton($this->getHandle(), $button) === Glfw::PRESS;
    }

    /**
     * Returns and forgets the key events (presses, releases and repeats) that arrived since the previous call.
     *
     * @return list<KeyEvent>
     */
    public function takeKeyEvents(): array
    {
        $events = $this->state->keyEvents;
        $this->state->keyEvents = [];

        return $events;
    }

    /**
     * @return array{float, float}
     */
    public function getCursorPosition(): array
    {
        $this->glfw->glfwGetCursorPos($this->getHandle(), \FFI::addr($this->x), \FFI::addr($this->y));

        $x = $this->x->cdata;
        $y = $this->y->cdata;
        \assert(\is_float($x) && \is_float($y));

        return [$x, $y];
    }

    /**
     * Hides the cursor and gives unlimited relative motion (the mouse look of a shooter).
     */
    public function setCursorCaptured(bool $isCaptured): void
    {
        $handle = $this->getHandle();

        if (!$isCaptured) {
            $this->glfw->glfwSetInputMode($handle, Glfw::CURSOR, Glfw::CURSOR_NORMAL);

            return;
        }

        $this->glfw->glfwSetInputMode($handle, Glfw::CURSOR, Glfw::CURSOR_DISABLED);

        if ($this->glfw->glfwRawMouseMotionSupported() === Glfw::TRUE) {
            $this->glfw->glfwSetInputMode($handle, Glfw::RAW_MOUSE_MOTION, Glfw::TRUE);
        }
    }

    /**
     * @param non-empty-string $title
     */
    public function setTitle(string $title): void
    {
        $this->glfw->glfwSetWindowTitle($this->getHandle(), $title);
    }

    /**
     * Seconds since GLFW was initialized.
     */
    public function getTime(): float
    {
        $time = $this->glfw->glfwGetTime();
        \assert(\is_float($time));

        return $time;
    }

    /**
     * @return list<non-empty-string>
     */
    public function getRequiredInstanceExtensions(): array
    {
        // GLFW knows the extensions only when it has found the Vulkan loader itself, which it does not look for in
        // the paths of Homebrew
        if (\PHP_OS_FAMILY === 'Darwin') {
            return ['VK_KHR_surface', 'VK_EXT_metal_surface'];
        }

        $count = $this->glfw->new('uint32_t');

        /** @var CData|null $names */
        $names = $this->glfw->glfwGetRequiredInstanceExtensions(\FFI::addr($count));
        $total = $count->cdata;
        \assert($names !== null && \is_int($total));

        $extensions = [];

        for ($i = 0; $i < $total; ++$i) {
            /** @var non-empty-string $name */
            $name = $names[$i];
            $extensions[] = $name;
        }

        return $extensions;
    }

    public function getWin32Handle(): CData
    {
        /** @var CData $hwnd */
        $hwnd = $this->glfw->glfwGetWin32Window($this->getHandle());

        return $hwnd;
    }

    /**
     * The `NSView` of the window on macOS.
     */
    public function getCocoaView(): CData
    {
        /** @var CData $view */
        $view = $this->glfw->glfwGetCocoaView($this->getHandle());

        return $view;
    }

    /**
     * Destroys the window and terminates GLFW, the window is not usable afterwards.
     */
    public function close(): void
    {
        if ($this->handle === null) {
            return;
        }

        $this->glfw->glfwDestroyWindow($this->handle);
        $this->glfw->glfwTerminate();
        $this->handle = null;
    }

    private function getHandle(): CData
    {
        return $this->handle ?? throw WindowException::becauseWindowNotCreated('the window is closed');
    }

    /**
     * @throws WindowException in case of the platform is not supported
     */
    private function load(): \FFI
    {
        try {
            return GLFW3::load();
        } catch (GlfwException $e) {
            throw WindowException::becausePlatformNotSupported(\PHP_OS_FAMILY, $e);
        }
    }
}
