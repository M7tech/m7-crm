import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseBusinessCard } from '../../resources/js/business-card-parser.js';

test('English card extracts editable fields without adding raw OCR to notes', () => {
    const fields = parseBusinessCard('Jane Smith\nSales Director\nExample Trading\njane@example.com\n+964 750 123 4567\nwww.example.com\nErbil, Iraq');
    assert.equal(fields.first_name, 'Jane');
    assert.equal(fields.last_name, 'Smith');
    assert.equal(fields.email, 'jane@example.com');
    assert.equal(fields.company_name, 'Example Trading');
    assert.equal(fields.phone, '+964 750 123 4567');
    assert.match(fields.notes, /Website: www.example.com/);
    assert.doesNotMatch(fields.notes, /Jane|OCR/);
});
test('Arabic and Sorani text and numerals survive parsing', () => {
    const fields = parseBusinessCard('محمد شاكر\nبەڕێوەبەری فرۆشتن\nکۆمپانیا\ninfo@example.iq\n+٩٦٤ ٧٥٠ ١٢٣ ٤٥٦٧\nهەولێر');
    assert.equal(fields.first_name, 'محمد');
    assert.equal(fields.last_name, 'شاكر');
    assert.equal(fields.phone, '+964 750 123 4567');
    assert.equal(fields.job_title, 'بەڕێوەبەری فرۆشتن');
});
test('Kurmanji accents are preserved', () => {
    const fields = parseBusinessCard('Şêrko Ahmed\nRêveber\nşerko@example.iq');
    assert.equal(fields.first_name, 'Şêrko');
    assert.equal(fields.job_title, 'Rêveber');
});
test('empty input leaves name blank for review', () => {
    assert.equal(parseBusinessCard('').first_name, '');
});
