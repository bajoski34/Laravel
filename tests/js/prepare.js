// Copy shared/ and one framework's folder into build/<framework>, the same way
// the flutterwave-<framework> publish tag lays them out in resources/js/components/flutterwave.
import { cpSync, rmSync } from 'node:fs';

const framework = process.argv[2];
const source = new URL('../../src/resources/js/', import.meta.url);
const target = new URL(`./build/${framework}/`, import.meta.url);

rmSync(target, { recursive: true, force: true });
cpSync(new URL('shared/', source), target, { recursive: true });
cpSync(new URL(`${framework}/`, source), target, { recursive: true });
