/**
 * Seeding helpers.
 *
 * `ensureBaseline()` find-or-creates one of every content type the front-end
 * specs visit, keyed by slug, so any spec can call it in `beforeAll` and get
 * the same ids back regardless of run order. `ContentTracker` covers
 * spec-specific content and guarantees cleanup.
 */
import type { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { resetFilterOverrides } from './filter-overrides';
import { resetPluginOptions } from './plugin-state';

interface RestPost {
	id: number;
	link: string;
	slug: string;
	author: number;
}

interface RestTerm {
	id: number;
	link: string;
	slug: string;
}

/**
 * REST bases of the fixture CPT and taxonomy.
 *
 * @see tests/e2e/fixtures/stf-test-cpt.php
 */
export const CPT = 'stf_test_cpt';
export const CPT_REST_BASE = 'stf-test-items';
export const TAX = 'stf_test_tax';
export const TAX_REST_BASE = 'stf-test-terms';

/**
 * Fixed date of the baseline post, so its date archive URL is stable.
 */
export const BASELINE_POST_DATE = '2024-03-15T10:00:00';

export interface Baseline {
	categoryId: number;
	categoryLink: string;
	tagId: number;
	tagLink: string;
	termId: number;
	termLink: string;
	postId: number;
	postLink: string;
	pageId: number;
	pageLink: string;
	blogPageId: number;
	blogPageLink: string;
	itemId: number;
	itemLink: string;
	authorId: number;
	/** Base-relative URLs for views with no REST `link`. */
	urls: {
		home: string;
		search: string;
		notFound: string;
		author: string;
		date: string;
		category: string;
		tag: string;
		cptArchive: string;
		taxArchive: string;
	};
}

async function findOrCreateTerm(
	requestUtils: RequestUtils,
	restBase: string,
	slug: string,
	name: string
): Promise< RestTerm > {
	const found = await requestUtils.rest< RestTerm[] >( {
		path: `/wp/v2/${ restBase }`,
		params: { slug, hide_empty: false },
	} );
	if ( found.length ) {
		return found[ 0 ];
	}
	return requestUtils.rest< RestTerm >( {
		method: 'POST',
		path: `/wp/v2/${ restBase }`,
		data: { slug, name },
	} );
}

async function findOrCreatePost(
	requestUtils: RequestUtils,
	restBase: string,
	slug: string,
	data: Record< string, unknown >
): Promise< RestPost > {
	const found = await requestUtils.rest< RestPost[] >( {
		path: `/wp/v2/${ restBase }`,
		params: { slug, status: 'publish', context: 'edit' },
	} );
	if ( found.length ) {
		return found[ 0 ];
	}
	return requestUtils.rest< RestPost >( {
		method: 'POST',
		path: `/wp/v2/${ restBase }`,
		data: { slug, status: 'publish', ...data },
	} );
}

/**
 * Find or create the shared baseline content and return its ids and URLs.
 * Idempotent and cheap after the first call.
 *
 * @param requestUtils Admin request utils.
 */
export async function ensureBaseline( requestUtils: RequestUtils ): Promise< Baseline > {
	const category = await findOrCreateTerm( requestUtils, 'categories', 'stf-e2e-cat', 'STF E2E Category' );
	const tag = await findOrCreateTerm( requestUtils, 'tags', 'stf-e2e-tag', 'STF E2E Tag' );
	const term = await findOrCreateTerm( requestUtils, TAX_REST_BASE, 'stf-e2e-term', 'STF E2E Term' );

	const post = await findOrCreatePost( requestUtils, 'posts', 'stf-e2e-post', {
		title: 'STF E2E Post',
		content: 'Baseline post for the Scripts to Footer e2e suite. stfsearchneedle',
		date: BASELINE_POST_DATE,
		categories: [ category.id ],
		tags: [ tag.id ],
		[ TAX_REST_BASE ]: [ term.id ],
	} );
	const page = await findOrCreatePost( requestUtils, 'pages', 'stf-e2e-page', {
		title: 'STF E2E Page',
		content: 'Baseline page for the Scripts to Footer e2e suite.',
	} );
	const blogPage = await findOrCreatePost( requestUtils, 'pages', 'stf-e2e-blog', {
		title: 'STF E2E Blog',
		content: '',
	} );
	const item = await findOrCreatePost( requestUtils, CPT_REST_BASE, 'stf-e2e-item', {
		title: 'STF E2E Item',
		content: 'Baseline custom post type item.',
		[ TAX_REST_BASE ]: [ term.id ],
	} );

	return {
		categoryId: category.id,
		categoryLink: category.link,
		tagId: tag.id,
		tagLink: tag.link,
		termId: term.id,
		termLink: term.link,
		postId: post.id,
		postLink: post.link,
		pageId: page.id,
		pageLink: page.link,
		blogPageId: blogPage.id,
		blogPageLink: blogPage.link,
		itemId: item.id,
		itemLink: item.link,
		authorId: post.author,
		urls: {
			home: '/',
			search: '/?s=stfsearchneedle',
			notFound: '/stf-e2e-this-page-does-not-exist/',
			author: `/?author=${ post.author }`,
			date: `/?m=${ BASELINE_POST_DATE.slice( 0, 4 ) }${ BASELINE_POST_DATE.slice( 5, 7 ) }`,
			category: `/?cat=${ category.id }`,
			tag: `/?tag=${ tag.slug }`,
			cptArchive: `/?post_type=${ CPT }`,
			taxArchive: `/?${ TAX }=${ term.slug }`,
		},
	};
}

/**
 * Restore the site-wide defaults every spec assumes: latest posts on the
 * front page, no plugin options, no filter overrides.
 *
 * @param requestUtils Admin request utils.
 */
export async function resetSite( requestUtils: RequestUtils ): Promise< void > {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/settings',
		data: { show_on_front: 'posts', page_on_front: 0, page_for_posts: 0 },
	} );
	await resetPluginOptions( requestUtils );
	await resetFilterOverrides( requestUtils );
}

/**
 * Show a static front page and a posts page.
 *
 * @param requestUtils Admin request utils.
 * @param baseline     Baseline content.
 */
export async function useStaticFrontPage( requestUtils: RequestUtils, baseline: Baseline ): Promise< void > {
	await requestUtils.rest( {
		method: 'POST',
		path: '/wp/v2/settings',
		data: {
			show_on_front: 'page',
			page_on_front: baseline.pageId,
			page_for_posts: baseline.blogPageId,
		},
	} );
}

/**
 * Tracks content a spec creates so `cleanup()` can delete all of it.
 */
export class ContentTracker {
	private posts: Array< { restBase: string; id: number } > = [];

	/**
	 * Create a published post (or any post type via `restBase`).
	 *
	 * @param requestUtils Request utils of the creating user.
	 * @param data         REST body.
	 * @param restBase     REST base, `posts` by default.
	 */
	async create(
		requestUtils: RequestUtils,
		data: Record< string, unknown >,
		restBase = 'posts'
	): Promise< RestPost > {
		const post = await requestUtils.rest< RestPost >( {
			method: 'POST',
			path: `/wp/v2/${ restBase }`,
			data: { status: 'publish', ...data },
		} );
		this.posts.push( { restBase, id: post.id } );
		return post;
	}

	/**
	 * Track a post created elsewhere (e.g. in the editor UI).
	 *
	 * @param id       Post ID.
	 * @param restBase REST base, `posts` by default.
	 */
	track( id: number, restBase = 'posts' ): void {
		this.posts.push( { restBase, id } );
	}

	/**
	 * Force-delete everything tracked. Never throws.
	 *
	 * @param requestUtils Admin request utils.
	 */
	async cleanup( requestUtils: RequestUtils ): Promise< void > {
		const posts = this.posts.splice( 0 );
		for ( const { restBase, id } of posts ) {
			await requestUtils
				.rest( { method: 'DELETE', path: `/wp/v2/${ restBase }/${ id }`, params: { force: true } } )
				.catch( () => undefined );
		}
	}
}
