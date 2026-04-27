<?php

namespace Tests\Unit\WhiteBox;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\PaymentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DomainExceptionsTest extends TestCase
{
    public function test_appointment_conflict_exception_is_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new AppointmentConflictException);
    }

    public function test_appointment_conflict_exception_carries_message(): void
    {
        $exception = new AppointmentConflictException('El barbero ya tiene una cita en este rango de tiempo.');

        $this->assertSame('El barbero ya tiene una cita en este rango de tiempo.', $exception->getMessage());
    }

    public function test_appointment_conflict_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(AppointmentConflictException::class);
        $this->expectExceptionMessage('Conflicto de cita');

        throw new AppointmentConflictException('Conflicto de cita');
    }

    public function test_insufficient_stock_exception_is_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new InsufficientStockException);
    }

    public function test_insufficient_stock_exception_carries_message(): void
    {
        $exception = new InsufficientStockException('No hay stock suficiente para registrar la salida.');

        $this->assertSame('No hay stock suficiente para registrar la salida.', $exception->getMessage());
    }

    public function test_insufficient_stock_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('Stock insuficiente');

        throw new InsufficientStockException('Stock insuficiente');
    }

    public function test_payment_exception_is_runtime_exception(): void
    {
        $this->assertInstanceOf(RuntimeException::class, new PaymentException);
    }

    public function test_payment_exception_carries_message(): void
    {
        $exception = new PaymentException('La cita ya tiene un pago registrado.');

        $this->assertSame('La cita ya tiene un pago registrado.', $exception->getMessage());
    }

    public function test_payment_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Error de pago');

        throw new PaymentException('Error de pago');
    }

    public function test_exceptions_support_custom_code(): void
    {
        $exception = new AppointmentConflictException('msg', 422);

        $this->assertSame(422, $exception->getCode());
    }

    public function test_exceptions_support_previous_throwable(): void
    {
        $previous = new RuntimeException('original');
        $exception = new PaymentException('wrapped', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
