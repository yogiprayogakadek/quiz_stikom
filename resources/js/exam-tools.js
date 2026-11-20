const MAX_EXPRESSION_LENGTH = 200;
const IPV4_SIZE = 2n ** 32n;

function tokenize(input) {
    const normalized = String(input)
        .replaceAll('×', '*')
        .replaceAll('÷', '/')
        .replaceAll('√', 'sqrt')
        .replace(/2\^\s*\(/g, 'pow2(');

    if (!normalized.trim()) throw new Error('Masukkan ekspresi terlebih dahulu.');
    if (normalized.length > MAX_EXPRESSION_LENGTH) throw new Error('Ekspresi terlalu panjang.');

    const tokens = [];
    let index = 0;

    while (index < normalized.length) {
        const rest = normalized.slice(index);
        const whitespace = rest.match(/^\s+/);
        if (whitespace) {
            index += whitespace[0].length;
            continue;
        }

        const number = rest.match(/^(?:\d+(?:\.\d*)?|\.\d+)/);
        if (number) {
            const value = Number(number[0]);
            if (!Number.isFinite(value)) throw new Error('Angka tidak valid.');
            tokens.push({ type: 'number', value });
            index += number[0].length;
            continue;
        }

        const identifier = rest.match(/^(sqrt|pow2)/);
        if (identifier) {
            tokens.push({ type: 'function', value: identifier[0] });
            index += identifier[0].length;
            continue;
        }

        const character = normalized[index];
        if ('+-*/()%'.includes(character)) {
            tokens.push({ type: character, value: character });
            index++;
            continue;
        }

        throw new Error(`Karakter “${character}” tidak valid.`);
    }

    return tokens;
}

export function calculateExpression(input) {
    const tokens = tokenize(input);
    let position = 0;

    const current = () => tokens[position];
    const consume = (type) => {
        if (current()?.type !== type) throw new Error('Ekspresi tidak lengkap atau kurung tidak seimbang.');
        return tokens[position++];
    };

    const parsePrimary = () => {
        let value;
        if (current()?.type === 'number') {
            value = consume('number').value;
        } else if (current()?.type === '(') {
            consume('(');
            value = parseExpression();
            consume(')');
        } else if (current()?.type === 'function') {
            const name = consume('function').value;
            consume('(');
            const argument = parseExpression();
            consume(')');
            if (name === 'sqrt' && argument < 0) throw new Error('Akar kuadrat bilangan negatif tidak didukung.');
            value = name === 'sqrt' ? Math.sqrt(argument) : 2 ** argument;
        } else {
            throw new Error('Ekspresi tidak valid.');
        }

        while (current()?.type === '%') {
            consume('%');
            value /= 100;
        }

        return value;
    };

    const parseUnary = () => {
        if (current()?.type === '+') {
            consume('+');
            return parseUnary();
        }
        if (current()?.type === '-') {
            consume('-');
            return -parseUnary();
        }
        return parsePrimary();
    };

    const parseTerm = () => {
        let value = parseUnary();
        while (current()?.type === '*' || current()?.type === '/') {
            const operator = tokens[position++].type;
            const operand = parseUnary();
            if (operator === '/' && operand === 0) throw new Error('Tidak dapat membagi dengan nol.');
            value = operator === '*' ? value * operand : value / operand;
        }
        return value;
    };

    function parseExpression() {
        let value = parseTerm();
        while (current()?.type === '+' || current()?.type === '-') {
            const operator = tokens[position++].type;
            const operand = parseTerm();
            value = operator === '+' ? value + operand : value - operand;
        }
        return value;
    }

    const result = parseExpression();
    if (position !== tokens.length) throw new Error('Ekspresi tidak valid.');
    if (!Number.isFinite(result)) throw new Error('Hasil perhitungan berada di luar batas.');

    return Number.parseFloat(result.toPrecision(12));
}

function parseIpv4(ip) {
    const parts = String(ip).trim().split('.');
    if (parts.length !== 4 || parts.some((part) => !/^\d{1,3}$/.test(part))) {
        throw new Error('Alamat IPv4 harus terdiri dari empat oktet.');
    }

    const octets = parts.map(Number);
    if (octets.some((octet) => octet < 0 || octet > 255)) {
        throw new Error('Setiap oktet IPv4 harus bernilai 0 sampai 255.');
    }

    return octets.reduce((value, octet) => (value << 8n) + BigInt(octet), 0n);
}

function formatIpv4(value) {
    return [24n, 16n, 8n, 0n]
        .map((shift) => Number((value >> shift) & 255n))
        .join('.');
}

export function calculateSubnet(ip, prefixInput) {
    const prefixText = String(prefixInput).trim();
    if (!/^\d{1,2}$/.test(prefixText)) throw new Error('Prefix harus berupa angka 0 sampai 32.');
    const prefix = Number(prefixText);
    if (prefix < 0 || prefix > 32) throw new Error('Prefix harus berada di antara /0 dan /32.');

    const address = parseIpv4(ip);
    const hostBits = 32 - prefix;
    const hostMask = hostBits === 32 ? IPV4_SIZE - 1n : (2n ** BigInt(hostBits)) - 1n;
    const mask = (IPV4_SIZE - 1n) ^ hostMask;
    const network = address & mask;
    const broadcast = network | hostMask;
    const total = 2n ** BigInt(hostBits);
    const relevantOctet = prefix === 32 ? 4 : Math.floor(prefix / 8) + 1;
    const maskOctet = Number((mask >> BigInt((4 - relevantOctet) * 8)) & 255n);

    let firstHost = network + 1n;
    let lastHost = broadcast - 1n;
    let usable = total - 2n;
    let note = 'Rentang host tidak mencakup alamat network dan broadcast.';

    if (prefix === 31) {
        firstHost = network;
        lastHost = broadcast;
        usable = 2n;
        note = '/31 umum dipakai untuk tautan point-to-point; kedua alamat dapat digunakan.';
    } else if (prefix === 32) {
        firstHost = network;
        lastHost = network;
        usable = 1n;
        note = '/32 mewakili satu alamat host tanpa rentang host terpisah.';
    }

    return {
        prefix,
        subnetMask: formatIpv4(mask),
        networkAddress: formatIpv4(network),
        broadcastAddress: formatIpv4(broadcast),
        firstHost: formatIpv4(firstHost),
        lastHost: formatIpv4(lastHost),
        totalAddresses: total.toString(),
        usableHosts: usable.toString(),
        blockSize: String(256 - maskOctet),
        relevantOctet,
        note,
    };
}
