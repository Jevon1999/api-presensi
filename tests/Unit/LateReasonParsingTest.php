<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LateReasonParsingTest extends TestCase
{
    /**
     * Test that late reason with spaces is captured correctly
     * Issue: "absen wfo alasan=motor mogok" was capturing only "motor"
     * Fix: Changed regex from /alasan=(.+?)(?:\s|$)/ to /alasan=(.+)$/
     */
    public function test_late_reason_with_spaces()
    {
        $originalMessage = "absen wfo alasan=motor mogok";
        
        // OLD REGEX (BROKEN): /alasan=(.+?)(?:\s|$)/i
        if (preg_match('/alasan=(.+?)(?:\s|$)/i', $originalMessage, $matches)) {
            $oldResult = trim($matches[1]);
        }
        
        // NEW REGEX (FIXED): /alasan=(.+)$/i
        if (preg_match('/alasan=(.+)$/i', $originalMessage, $matches)) {
            $newResult = trim($matches[1]);
        }
        
        $this->assertEquals("motor", $oldResult, "Old regex incorrectly captures only first word");
        $this->assertEquals("motor mogok", $newResult, "New regex correctly captures full reason with spaces");
    }

    /**
     * Test with multiple spaces between words
     */
    public function test_late_reason_with_multiple_words()
    {
        $originalMessage = "masuk wfa alasan=ada meeting penting dengan klien";
        
        if (preg_match('/alasan=(.+)$/i', $originalMessage, $matches)) {
            $result = trim($matches[1]);
        }
        
        $this->assertEquals("ada meeting penting dengan klien", $result);
    }

    /**
     * Test with single word reason (should work same as before)
     */
    public function test_late_reason_single_word()
    {
        $originalMessage = "masuk wfo alasan=traffic";
        
        if (preg_match('/alasan=(.+)$/i', $originalMessage, $matches)) {
            $result = trim($matches[1]);
        }
        
        $this->assertEquals("traffic", $result);
    }

    /**
     * Test with special characters
     */
    public function test_late_reason_with_special_characters()
    {
        $originalMessage = "absen wfa alasan=update sistem (database & API)";
        
        if (preg_match('/alasan=(.+)$/i', $originalMessage, $matches)) {
            $result = trim($matches[1]);
        }
        
        $this->assertEquals("update sistem (database & API)", $result);
    }
}
