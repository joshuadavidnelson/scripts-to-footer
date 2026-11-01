/**
 * Block editor helpers shared by the meta box and role specs.
 */
import type { Page } from '@playwright/test';

/**
 * Click the editor's save/update button and wait for the follow-up meta box
 * save request Gutenberg posts after the main entity save.
 *
 * @param page Editor page.
 */
export async function saveWithMetaBoxes( page: Page ): Promise< void > {
	const metaBoxSaved = page.waitForResponse(
		( response ) =>
			response.url().includes( 'meta-box-loader=1' ) &&
			response.request().method() === 'POST'
	);
	await page.locator( '.editor-post-publish-button' ).click();
	await metaBoxSaved;
}

/**
 * Expand the collapsed "Meta Boxes" area at the bottom of the block editor,
 * so the meta box fields are visible and interactable. No-op when open.
 *
 * @param page Editor page.
 */
export async function openMetaBoxes( page: Page ): Promise< void > {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes', exact: true } );
	await toggle.waitFor();
	if ( 'true' !== ( await toggle.getAttribute( 'aria-expanded' ) ) ) {
		// A centered drag handle overlays the middle of the bar; click its left edge.
		await toggle.click( { position: { x: 20, y: 10 } } );
	}
}
