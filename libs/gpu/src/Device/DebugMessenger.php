<?php

declare(strict_types=1);

namespace Local\GPU\Device;

use FFI\CData;
use Local\GPU\DeviceOptions;
use Serafim\Vulkan\Enum\VkDebugUtilsMessageSeverityFlagBitsEXT;
use Serafim\Vulkan\Enum\VkDebugUtilsMessageTypeFlagBitsEXT;
use Serafim\Vulkan\Handle\VkDebugUtilsMessengerEXT;
use Serafim\Vulkan\Handle\VkInstance;
use Serafim\Vulkan\Struct\VkDebugUtilsMessengerCreateInfoEXT;
use Serafim\Vulkan\Vulkan;

/**
 * The messenger that hands the warnings and the errors of the validation layer to a callback, or to `STDERR`.
 */
final class DebugMessenger
{
    private function __construct() {}

    public static function create(Vulkan $vk, VkInstance $instance, DeviceOptions $options): VkDebugUtilsMessengerEXT
    {
        $report = $options->onValidationMessage ?? static function (string $message): void {
            \fwrite(\STDERR, $message . "\n");
        };

        return $vk->vkCreateDebugUtilsMessengerEXT($instance, new VkDebugUtilsMessengerCreateInfoEXT(
            pfnUserCallback: static function (
                int $severity,
                int $types,
                ?CData $data,
                ?CData $user,
            ) use ($report): int {
                if ($data !== null) {
                    /** @var string $message */
                    $message = $data->pMessage;
                    $report($message);
                }

                return 0;
            },
            messageSeverity: VkDebugUtilsMessageSeverityFlagBitsEXT::VK_DEBUG_UTILS_MESSAGE_SEVERITY_WARNING_BIT_EXT
                | VkDebugUtilsMessageSeverityFlagBitsEXT::VK_DEBUG_UTILS_MESSAGE_SEVERITY_ERROR_BIT_EXT,
            messageType: VkDebugUtilsMessageTypeFlagBitsEXT::VK_DEBUG_UTILS_MESSAGE_TYPE_GENERAL_BIT_EXT
                | VkDebugUtilsMessageTypeFlagBitsEXT::VK_DEBUG_UTILS_MESSAGE_TYPE_VALIDATION_BIT_EXT
                | VkDebugUtilsMessageTypeFlagBitsEXT::VK_DEBUG_UTILS_MESSAGE_TYPE_PERFORMANCE_BIT_EXT,
        ));
    }
}
