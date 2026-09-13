<?php
declare(strict_types=1);

final class DbfReader
{
    public function rows(string $file, array $fields): array
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) throw new RuntimeException('No se pudo leer una tabla de Avalon. Verifique la ruta y los permisos de lectura.');
        try {
            [$records, $start, $length, $definition] = $this->definition($handle, $fields);
            $rows = []; fseek($handle, $start);
            for ($i = 0; $i < $records; $i++) {
                $record = $this->read($handle, $length);
                if ($record[0] === '*') continue;
                $rows[] = $this->record($record, $definition, $file);
            }
            return $rows;
        } finally { fclose($handle); }
    }

    /** Lee únicamente los registros cuyo campo coincida exactamente, sin cargar toda la tabla en memoria. */
    public function findExact(string $file, array $fields, string $matchField, string $value): array
    {
        $fields = array_values(array_unique([...$fields, $matchField]));
        $handle = @fopen($file, 'rb');
        if (!$handle) throw new RuntimeException('No se pudo leer una tabla de Avalon. Verifique la ruta y los permisos de lectura.');
        try {
            [$records, $start, $length, $definition] = $this->definition($handle, $fields);
            $match = $definition[$matchField]; $rows = []; fseek($handle, $start);
            for ($i = 0; $i < $records; $i++) {
                $record = $this->read($handle, $length);
                if ($record[0] === '*') continue;
                $candidate = $this->decode(substr($record, $match[0], $match[1]), $match[2], $file);
                if (trim($candidate) === trim($value)) $rows[] = $this->record($record, $definition, $file);
            }
            return $rows;
        } finally { fclose($handle); }
    }

    /** Busca texto contenido en un campo sin materializar todos los registros de la tabla. */
    public function findContains(string $file, array $fields, string $matchField, string $value): array
    {
        $fields = array_values(array_unique([...$fields, $matchField]));
        $handle = @fopen($file, 'rb');
        if (!$handle) throw new RuntimeException('No se pudo leer una tabla de Avalon. Verifique la ruta y los permisos de lectura.');
        try {
            [$records, $start, $length, $definition] = $this->definition($handle, $fields);
            $match = $definition[$matchField]; $rows = []; fseek($handle, $start);
            for ($i = 0; $i < $records; $i++) {
                $record = $this->read($handle, $length);
                if ($record[0] === '*') continue;
                $candidate = $this->decode(substr($record, $match[0], $match[1]), $match[2], $file);
                if (stripos($candidate, $value) !== false) $rows[] = $this->record($record, $definition, $file);
            }
            return $rows;
        } finally { fclose($handle); }
    }

    private function definition($handle, array $fields): array
    {
        $header = $this->read($handle, 32);
        $records = unpack('V', substr($header, 4, 4))[1];
        $start = unpack('v', substr($header, 8, 2))[1];
        $length = unpack('v', substr($header, 10, 2))[1];
        if ($records > 1000000 || $start < 33 || $length < 2) throw new RuntimeException('La tabla de Avalon tiene un formato no válido.');
        $offset = 1; $definition = [];
        while (ftell($handle) < $start) {
            $first = $this->read($handle, 1);
            if ($first === "\r") break;
            $descriptor = $first . $this->read($handle, 31);
            $name = rtrim(substr($descriptor, 0, 11), "\0");
            $width = ord($descriptor[16]);
            if (in_array($name, $fields, true)) $definition[$name] = [$offset, $width, $descriptor[11]];
            $offset += $width;
        }
        if (array_diff($fields, array_keys($definition))) throw new RuntimeException('La estructura de una tabla de Avalon no coincide con la esperada.');
        return [$records, $start, $length, $definition];
    }

    private function record(string $record, array $definition, string $file): array
    {
        $row = [];
        foreach ($definition as $name => $field) $row[$name] = $this->decode(substr($record, $field[0], $field[1]), $field[2], $file);
        return $row;
    }

    private function decode(string $value, string $type, string $file): string
    {
        $value = trim($value, " \0");
        if ($type === 'M') return $this->memo($file, $value);
        if ($type === 'L') return in_array(strtoupper($value), ['T', 'Y'], true) ? 'T' : 'F';
        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
    private function memo(string $file, string $pointer): string
    {
        if ($pointer === '') return '';
        $block = unpack('V', str_pad($pointer, 4, "\0"))[1];
        $memo = preg_replace('/\.dbf$/i', '.FPT', $file);
        $handle = @fopen($memo, 'rb');
        if (!$handle || $block === 0) return '';
        try {
            $header = $this->read($handle, 8);
            $blockSize = unpack('n', substr($header, 6, 2))[1];
            if ($blockSize < 32 || $blockSize > 65535) return '';
            fseek($handle, $block * $blockSize);
            $this->read($handle, 4); // Tipo de bloque de FoxPro.
            $length = unpack('N', $this->read($handle, 4))[1];
            if ($length > 1000000) return '';
            return trim(mb_convert_encoding($this->read($handle, $length), 'UTF-8', 'Windows-1252'));
        } catch (RuntimeException $exception) { return ''; } finally { fclose($handle); }
    }
    private function read($handle, int $size): string
    {
        $out = ''; while (strlen($out) < $size) { $part = fread($handle, $size - strlen($out)); if ($part === false || $part === '') throw new RuntimeException('La lectura de Avalon quedó incompleta.'); $out .= $part; } return $out;
    }
}
