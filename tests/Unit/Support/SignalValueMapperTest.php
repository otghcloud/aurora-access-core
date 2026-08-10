<?php

use App\Support\SignalValueMapper;

it('normalizes common true values to canonical true', function () {
    expect(SignalValueMapper::toCanonicalBool(1))->toBeTrue();
    expect(SignalValueMapper::toCanonicalBool('1'))->toBeTrue();
    expect(SignalValueMapper::toCanonicalBool('true'))->toBeTrue();
    expect(SignalValueMapper::toCanonicalBool('ON'))->toBeTrue();
});

it('normalizes common false values to canonical false', function () {
    expect(SignalValueMapper::toCanonicalBool(0))->toBeFalse();
    expect(SignalValueMapper::toCanonicalBool('0'))->toBeFalse();
    expect(SignalValueMapper::toCanonicalBool('false'))->toBeFalse();
    expect(SignalValueMapper::toCanonicalBool('off'))->toBeFalse();
});

it('returns null for unknown wire values', function () {
    expect(SignalValueMapper::toCanonicalBool('maybe'))->toBeNull();
    expect(SignalValueMapper::toCanonicalBool(2))->toBeNull();
});

it('reverses input normalization when signal is reversed', function () {
    expect(SignalValueMapper::toCanonicalBool(1, true))->toBeFalse();
    expect(SignalValueMapper::toCanonicalBool(0, true))->toBeTrue();
});

it('maps canonical output values to wire values with optional reversal', function () {
    expect(SignalValueMapper::fromCanonicalBool(true))->toBe(1);
    expect(SignalValueMapper::fromCanonicalBool(false))->toBe(0);
    expect(SignalValueMapper::fromCanonicalBool(true, true))->toBe(0);
    expect(SignalValueMapper::fromCanonicalBool(false, true))->toBe(1);
});

it('supports custom wire values', function () {
    expect(SignalValueMapper::fromCanonicalBool(true, false, 'OPEN', 'CLOSED'))->toBe('OPEN');
    expect(SignalValueMapper::fromCanonicalBool(true, true, 'OPEN', 'CLOSED'))->toBe('CLOSED');
});
