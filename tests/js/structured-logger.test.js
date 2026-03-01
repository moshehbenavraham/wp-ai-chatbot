import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { createLogger } from '../../lib/structured-logger.js';

describe('structured-logger', () => {
	let logsDir;

	beforeEach(() => {
		logsDir = fs.mkdtempSync(path.join(os.tmpdir(), 'structured-logger-'));
	});

	afterEach(() => {
		// Clean up temp files.
		const files = fs.readdirSync(logsDir);
		for (const file of files) {
			fs.unlinkSync(path.join(logsDir, file));
		}
		fs.rmdirSync(logsDir);
	});

	it('captureError writes a valid JSON file', () => {
		const log = createLogger('test-component', logsDir);
		const err = new Error('Something went wrong');

		const filePath = log.captureError(err, { userId: 42 });

		expect(filePath).toBeTruthy();
		expect(fs.existsSync(filePath)).toBe(true);

		const content = JSON.parse(fs.readFileSync(filePath, 'utf-8'));

		expect(content.level).toBe('error');
		expect(content.component).toBe('test-component');
		expect(content.msg).toBe('Something went wrong');
		expect(content.error.type).toBe('Error');
		expect(content.error.message).toBe('Something went wrong');
		expect(content.error.stack).toBeTruthy();
		expect(content.context.userId).toBe(42);
	});

	it('captureError creates directory if missing', () => {
		const nested = path.join(logsDir, 'nested');
		const log = createLogger('test', nested);

		const filePath = log.captureError(new Error('dir test'));

		expect(fs.existsSync(nested)).toBe(true);
		expect(filePath).toBeTruthy();

		// Clean up nested dir.
		fs.unlinkSync(filePath);
		fs.rmdirSync(nested);
	});

	it('logger has all level methods', () => {
		const log = createLogger('test', logsDir);

		expect(typeof log.debug).toBe('function');
		expect(typeof log.info).toBe('function');
		expect(typeof log.warn).toBe('function');
		expect(typeof log.error).toBe('function');
		expect(typeof log.captureError).toBe('function');
	});
});
