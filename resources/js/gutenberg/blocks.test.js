import fs from 'node:fs';
import { describe, expect, it } from 'vitest';

describe('registerGutenbergBlocks', () => {
    it('enables experimental and form core blocks before registering block-library blocks', () => {
        const source = fs.readFileSync('resources/js/gutenberg/blocks.jsx', 'utf8');
        const experimentsIndex = source.indexOf('window.__experimentalEnableBlockExperiments = true');
        const formsIndex = source.indexOf('window.__experimentalEnableFormBlocks = true');
        const registerIndex = source.indexOf('registerCoreBlocks();');

        expect(experimentsIndex).toBeGreaterThan(-1);
        expect(formsIndex).toBeGreaterThan(-1);
        expect(registerIndex).toBeGreaterThan(-1);
        expect(experimentsIndex).toBeLessThan(registerIndex);
        expect(formsIndex).toBeLessThan(registerIndex);
    });
});
