<?php
/**
 * 音频时长解析：从原始音频数据计算时长
 */
class AudioParser
{
    public static function parseDuration($data)
    {
        /* PCM16 48000Hz 单声道 */
        $len = strlen($data);
        if ($len < 44) {
            return 0;
        }
        $header = substr($data, 0, 4);
        if ($header === 'RIFF') {
            $channels = unpack('v', substr($data, 22, 2))[1];
            $sampleRate = unpack('V', substr($data, 24, 4))[1];
            $bitsPerSample = unpack('v', substr($data, 34, 2))[1];
            $dataSize = unpack('V', substr($data, 40, 4))[1];
            if ($sampleRate > 0 && $bitsPerSample > 0 && $channels > 0) {
                return round($dataSize / ($sampleRate * $channels * ($bitsPerSample / 8)), 2);
            }
        }
        /* 粗略估算：假设 16kHz 16bit 单声道 */
        return round($len / 32000, 2);
    }
}