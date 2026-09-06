<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelperAliasTest extends TestCase
{
    public function test_helper_alias_resolves_to_app_helpers_class(): void
    {
        $this->assertTrue(class_exists('Helper'));
        $this->assertTrue(method_exists('Helper', 'appClasses'));
        $this->assertTrue(is_a(new \Helper(), \App\Helpers\Helpers::class));
        $this->assertIsArray(\Helper::appClasses());
    }
}
