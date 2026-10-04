<?php

namespace Tests\Unit\Api;

use App\Api\GtfsRealtime\Protobuf;
use PHPUnit\Framework\TestCase;

class ProtobufTest extends TestCase
{
    public function test_varint_usa_siete_bits_por_byte(): void
    {
        $this->assertSame("\x00", Protobuf::varint(0));
        $this->assertSame("\x01", Protobuf::varint(1));
        $this->assertSame("\x7F", Protobuf::varint(127));
        $this->assertSame("\x80\x01", Protobuf::varint(128));
        // El ejemplo de la documentación oficial del formato: 300 se escribe 0xAC 0x02.
        $this->assertSame("\xAC\x02", Protobuf::varint(300));
        $this->assertSame("\xFF\xFF\xFF\xFF\x0F", Protobuf::varint(4294967295));
    }

    public function test_un_timestamp_de_unix_cabe_en_cinco_bytes(): void
    {
        $this->assertSame(5, strlen(Protobuf::varint(1791078000)));
    }

    public function test_no_acepta_negativos(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Protobuf::varint(-1);
    }

    public function test_el_entero_lleva_etiqueta_con_el_numero_de_campo_y_el_tipo(): void
    {
        // Ejemplo oficial: el campo 1 con el valor 150 es 08 96 01.
        $this->assertSame("\x08\x96\x01", Protobuf::entero(1, 150));
    }

    public function test_el_texto_lleva_su_largo(): void
    {
        // Ejemplo oficial: el campo 2 con "testing" es 12 07 "testing".
        $this->assertSame("\x12\x07testing", Protobuf::texto(2, 'testing'));
    }

    public function test_el_largo_cuenta_bytes_y_no_letras(): void
    {
        $this->assertSame("\x0A\x03\xC3\xB1\x61", Protobuf::texto(1, 'ña'));
    }

    public function test_los_decimales_van_en_little_endian(): void
    {
        $this->assertSame("\x0D\x00\x00\x80\x3F", Protobuf::decimal32(1, 1.0));
        $this->assertSame("\x09\x00\x00\x00\x00\x00\x00\xF0\x3F", Protobuf::decimal64(1, 1.0));
    }

    public function test_un_mensaje_anidado_es_un_bloque_con_largo(): void
    {
        $interior = Protobuf::entero(1, 150);

        $this->assertSame("\x1A\x03\x08\x96\x01", Protobuf::mensaje(3, $interior));
    }
}
