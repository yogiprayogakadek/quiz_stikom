import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateExpression, calculateSubnet } from '../../resources/js/exam-tools.js';

test('kalkulator mengikuti prioritas operator dan kurung', () => {
    assert.equal(calculateExpression('2 + 3 × 4'), 14);
    assert.equal(calculateExpression('(2 + 3) × 4'), 20);
});

test('kalkulator mendukung persen, akar, dan pangkat dua', () => {
    assert.equal(calculateExpression('25%'), 0.25);
    assert.equal(calculateExpression('√(81)'), 9);
    assert.equal(calculateExpression('2^(5)'), 32);
});

test('kalkulator menolak pembagian nol dan input asing', () => {
    assert.throws(() => calculateExpression('5 / 0'), /nol/);
    assert.throws(() => calculateExpression('alert(1)'), /tidak valid/);
});

test('subnet menghitung /24 dan /27', () => {
    assert.deepEqual(calculateSubnet('192.168.10.44', 24), {
        prefix: 24,
        subnetMask: '255.255.255.0',
        networkAddress: '192.168.10.0',
        broadcastAddress: '192.168.10.255',
        firstHost: '192.168.10.1',
        lastHost: '192.168.10.254',
        totalAddresses: '256',
        usableHosts: '254',
        blockSize: '256',
        relevantOctet: 4,
        note: 'Rentang host tidak mencakup alamat network dan broadcast.',
    });

    const subnet27 = calculateSubnet('192.168.10.44', 27);
    assert.equal(subnet27.networkAddress, '192.168.10.32');
    assert.equal(subnet27.broadcastAddress, '192.168.10.63');
    assert.equal(subnet27.blockSize, '32');
    assert.equal(subnet27.relevantOctet, 4);
});

test('subnet mendukung lintas oktet, /31, dan /32', () => {
    const subnet20 = calculateSubnet('172.16.31.9', 20);
    assert.equal(subnet20.networkAddress, '172.16.16.0');
    assert.equal(subnet20.broadcastAddress, '172.16.31.255');
    assert.equal(subnet20.blockSize, '16');
    assert.equal(subnet20.relevantOctet, 3);

    const subnet31 = calculateSubnet('10.0.0.8', 31);
    assert.equal(subnet31.usableHosts, '2');
    assert.match(subnet31.note, /point-to-point/);

    const subnet32 = calculateSubnet('10.0.0.8', 32);
    assert.equal(subnet32.firstHost, '10.0.0.8');
    assert.equal(subnet32.lastHost, '10.0.0.8');
    assert.equal(subnet32.usableHosts, '1');
});

test('subnet menolak alamat dan prefix tidak valid', () => {
    assert.throws(() => calculateSubnet('192.168.999.1', 24), /oktet/);
    assert.throws(() => calculateSubnet('192.168.1', 24), /empat oktet/);
    assert.throws(() => calculateSubnet('192.168.1.1', 33), /\/0 dan \/32/);
});
