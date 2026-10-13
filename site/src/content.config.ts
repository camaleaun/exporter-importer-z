import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const schema = z.object( {
	title: z.string(),
	description: z.string(),
	toc: z.boolean().default( true ),
} );

// The docs, in Markdown: src/content/docs/<group>/<page>.md.
const docs = defineCollection( {
	loader: glob( { base: './src/content/docs', pattern: '**/*.md' } ),
	schema,
} );

// Their translations, put by scripts/translations.mjs (git-ignored):
// src/content/i18n/<code>/docs/<group>/<page>.md. `translatedFrom` is the
// commit of the English page they were translated from.
const docsI18n = defineCollection( {
	loader: glob( { base: './src/content/i18n', pattern: '*/docs/**/*.md' } ),
	schema: schema.extend( { translatedFrom: z.string().optional() } ),
} );

export const collections = { docs, docsI18n };
