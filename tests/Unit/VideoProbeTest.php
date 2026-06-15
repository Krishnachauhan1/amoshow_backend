<?php

namespace Tests\Unit;

use App\Support\VideoProbe;
use PHPUnit\Framework\TestCase;

class VideoProbeTest extends TestCase
{
    public function test_reads_mp4_mvhd_duration(): void
    {
        $mvhd = pack('N', 100) . 'mvhd';
        $mvhd .= pack('C', 0) . pack('CCC', 0, 0, 0);
        $mvhd .= pack('NNNN', 0, 0, 1000, 15000);
        $mvhd .= str_repeat("\0", 72);

        $moov = pack('N', strlen($mvhd) + 8) . 'moov' . $mvhd;
        $ftyp = pack('N', 20) . 'ftyp' . 'mp42' . pack('NN', 0, 0);

        $file = sys_get_temp_dir() . '/video_probe_test.mp4';
        file_put_contents($file, $ftyp . $moov);

        $this->assertSame(15, VideoProbe::durationSeconds($file));

        unlink($file);
    }

    public function test_reads_duration_when_moov_is_at_end_of_file(): void
    {
        $mvhd = pack('N', 100) . 'mvhd';
        $mvhd .= pack('C', 0) . pack('CCC', 0, 0, 0);
        $mvhd .= pack('NNNN', 0, 0, 1000, 45000);
        $mvhd .= str_repeat("\0", 72);

        $moov = pack('N', strlen($mvhd) + 8) . 'moov' . $mvhd;
        $ftyp = pack('N', 20) . 'ftyp' . 'mp42' . pack('NN', 0, 0);
        $mdat = pack('N', 16) . 'mdat' . pack('NN', 0, 0);

        $file = sys_get_temp_dir() . '/video_probe_end_test.mp4';
        file_put_contents($file, $ftyp . $mdat . $moov);

        $this->assertSame(45, VideoProbe::durationSeconds($file));

        unlink($file);
    }
}
