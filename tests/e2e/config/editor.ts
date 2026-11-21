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
 * Make the meta box area at the bottom of the block editor visible, so the
 * meta box fields are interactable.
 *
 * Newer editors (WP 6.8+) collapse it behind a "Meta Boxes" toggle; older
 * ones (e.g. the WP 6.2 floor) render the meta boxes inline with no toggle.
 * Whichever appears first decides: expand the toggle if it is collapsed,
 * otherwise there is nothing to do.
 *
 * @param page Editor page.
 */
export async function openMetaBoxes( page: Page ): Promise< void > {
	const toggle = page.getByRole( 'button', { name: 'Meta Boxes', exact: true } );
	const inlineArea = page.locator( '.edit-post-layout__metaboxes' );
	await toggle.or( inlineArea ).first().waitFor();
	if ( ! ( await toggle.isVisible() ) ) {
		return;
	}
	if ( 'true' !== ( await toggle.getAttribute( 'aria-expanded' ) ) ) {
		// A centered drag handle overlays the middle of the bar; click its left edge.
		await toggle.click( { position: { x: 20, y: 10 } } );
	}
}
