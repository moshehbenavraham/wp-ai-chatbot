import { defineConfig } from 'vitest/config';

export default defineConfig({
	test: {
		include: ['tests/js/**/*.test.js'],
		// Default environment for Node-based tests (structured-logger, smoke).
		// Individual test files may override with @vitest-environment happy-dom.
		environment: 'node',
		environmentOptions: {
			happyDOM: {
				url: 'http://localhost/',
			},
		},
	},
});
