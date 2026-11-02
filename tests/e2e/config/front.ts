/**
 * Small front-end helpers shared by the placement specs.
 */
import type { ScriptPlacement } from './scripts';
import { getPlacement, visitorContext } from './scripts';

/**
 * Fetch one front-end URL as a logged-out visitor and report where its
 * scripts printed. Opens and disposes its own request context, so specs
 * never leak one.
 *
 * @param baseURL        Site base URL.
 * @param url            Absolute or base-relative URL.
 * @param expectedStatus Expected HTTP status (200 unless testing a 404).
 */
export async function fetchPlacement(
	baseURL: string | undefined,
	url: string,
	expectedStatus = 200
): Promise< ScriptPlacement > {
	const visitor = await visitorContext( baseURL );
	try {
		return await getPlacement( visitor, url, expectedStatus );
	} finally {
		await visitor.dispose();
	}
}
