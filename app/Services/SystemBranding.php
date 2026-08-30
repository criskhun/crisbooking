<?php

namespace App\Services;

use App\Models\SystemSetting;
use Throwable;

class SystemBranding
{
    private ?SystemSetting $settings = null;

    public function settings(): SystemSetting
    {
        if ($this->settings) {
            return $this->settings;
        }

        try {
            return $this->settings = SystemSetting::query()->first() ?? SystemSetting::fallback();
        } catch (Throwable) {
            return $this->settings = SystemSetting::fallback();
        }
    }

    public function forget(): void
    {
        $this->settings = null;
    }
}
