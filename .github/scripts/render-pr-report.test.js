'use strict';

const { test, describe } = require( 'node:test' );
const assert = require( 'node:assert/strict' );
const { execFileSync, spawnSync } = require( 'node:child_process' );
const fs = require( 'node:fs' );
const os = require( 'node:os' );
const path = require( 'node:path' );

const { renderQualitySection, renderMetricsSection, renderCoverageSection, renderPrReport } = require( './render-pr-report' );

const SCRIPT = path.resolve( __dirname, 'render-pr-report.js' );
const HEAD_SHA = 'aaaaaaa1111111111111111111111111111111111';
const BASE_SHA = 'bbbbbbb2222222222222222222222222222222222';
const RUN_URL = 'https://github.com/o/r/actions/runs/1';

const MAX = {
	critical: 0,
	error: 2,
	avgCyclomaticComplexityByClass: 14,
	logicalLinesByMethod: 14,
	lackCohesionOfMethods: 2.5,
	avgEfferentCoupling: 2,
};
const FLOORS = { php: { lines: 90, methods: 90 } };

const metricsInput = ( head = {}, base = {}, max = MAX ) => ( {
	head: { OOP: { classes: 5 }, ...head },
	base: { OOP: { classes: 5 }, ...base },
	max,
} );
const row = ( body, label ) => body.split( '\n' ).find( ( line ) => line.startsWith( `| ${ label } |` ) );
const metric = ( covered, total, pct ) => ( { covered, total, pct } );

describe( 'renderQualitySection', () => {
	test( 'renders a Check | Result row per tool with a pass or fail mark', () => {
		const body = renderQualitySection( {
			phpcs: { outcome: 'success' },
			phpstan: { outcome: 'failure' },
			phpmd: { outcome: 'success' },
		} );
		assert.match( body, /^### PHP code quality\n\| Check \| Result \|/ );
		assert.equal( row( body, 'PHPCS' ), '| PHPCS | ✅ |' );
		assert.equal( row( body, 'PHPStan' ), '| PHPStan | ❌ |' );
		assert.equal( row( body, 'PHPMD' ), '| PHPMD | ✅ |' );
	} );

	test( 'shows skipped and unknown outcomes without a pass or fail mark', () => {
		const body = renderQualitySection( { phpcs: { outcome: 'skipped' }, phpstan: { outcome: 'cancelled' } } );
		assert.equal( row( body, 'PHPCS' ), '| PHPCS | ⏭️ Skipped |' );
		assert.equal( row( body, 'PHPStan' ), '| PHPStan | 🚫 Cancelled |' );
		assert.equal( row( body, 'PHPMD' ), '| PHPMD | — |' );
	} );
} );

describe( 'renderMetricsSection', () => {
	test( 'renders a five-column diff table against a comparable base', () => {
		const body = renderMetricsSection( metricsInput() );
		assert.match( body, /### Code Metrics/ );
		assert.match( body, /\| Metric \| Base \| PR \| Δ \| Max \|/ );
	} );

	test( 'reports no regression and no breach when nothing moved', () => {
		const same = { Complexity: { avgCyclomaticComplexityByClass: 6 } };
		const body = renderMetricsSection( metricsInput( same, same ) );
		assert.match( body, /✅ No metric moved in the wrong direction\. All within threshold\./ );
		assert.equal( row( body, 'Avg cyclomatic complexity / class' ), '| Avg cyclomatic complexity / class | 6 | 6 | — | 14 ✅ |' );
	} );

	test( 'flags a metric that rose while still under its ceiling', () => {
		const body = renderMetricsSection(
			metricsInput( { Complexity: { avgCyclomaticComplexityByClass: 7.5 } }, { Complexity: { avgCyclomaticComplexityByClass: 6 } } )
		);
		assert.match( body, /⚠️ 1 metric moved in the wrong direction\. All within threshold\./ );
		assert.equal( row( body, 'Avg cyclomatic complexity / class' ), '| Avg cyclomatic complexity / class | 6 | 7.50 | ⚠️ +1.50 | 14 ✅ |' );
	} );

	test( 'does not flag a metric that fell', () => {
		const body = renderMetricsSection(
			metricsInput( { Complexity: { avgCyclomaticComplexityByClass: 5 } }, { Complexity: { avgCyclomaticComplexityByClass: 6 } } )
		);
		assert.match( row( body, 'Avg cyclomatic complexity / class' ), /\| -1 \|/ );
		assert.match( body, /No metric moved in the wrong direction/ );
	} );

	test( 'flags a breach of a ceiling with a cross', () => {
		const same = { Violations: { error: 3 } };
		const body = renderMetricsSection( metricsInput( same, same ) );
		assert.equal( row( body, 'Violations: error' ), '| Violations: error | 3 | 3 | — | 2 ❌ |' );
		assert.match( body, /❌ No metric moved in the wrong direction\. 1 metric breached threshold\./ );
	} );

	test( 'omits a metric absent from both summaries and shows n/a when only the base has it', () => {
		const body = renderMetricsSection( metricsInput( {}, { Coupling: { avgEfferentCoupling: 1 } } ) );
		assert.equal( row( body, 'Lack of cohesion of methods' ), undefined );
		assert.match( row( body, 'Avg efferent coupling' ), /\| 1 \| n\/a \| — \| 2 \|/ );
	} );

	test( 'throws a clear error for an unknown key in metrics.max', () => {
		assert.throws( () => renderMetricsSection( metricsInput( {}, {}, { bogus: 1 } ) ), /no entry in CATALOGUE/ );
	} );

	test( 'falls back to a three-column table when the base declares no classes', () => {
		const body = renderMetricsSection( {
			head: { OOP: { classes: 5 }, Violations: { error: 3 } },
			base: { OOP: { classes: 0 } },
			max: MAX,
		} );
		assert.match( body, /Showing this branch on its own/ );
		assert.match( body, /\| Metric \| PR \| Max \|/ );
		assert.equal( row( body, 'Violations: error' ), '| Violations: error | 3 | 2 ❌ |' );
	} );
} );

describe( 'renderCoverageSection', () => {
	const head = { php: { lines: metric( 95, 100, 95 ), methods: metric( 8, 10, 80 ), branches: null } };
	const base = { php: { lines: metric( 94, 100, 94 ), methods: metric( 8, 10, 80 ), branches: null } };

	test( 'renders a diff table with floors, and omits a metric neither side measured', () => {
		const body = renderCoverageSection( { head, base, floors: FLOORS } );
		assert.match( body, /\| Metric \| Base \| PR \| Δ \| Floor \|/ );
		assert.equal( row( body, 'Lines' ), '| Lines | 94% | 95% | +1% | 90% ✅ |' );
		assert.equal( row( body, 'Methods' ), '| Methods | 80% | 80% | — | 90% ❌ |' );
		assert.equal( row( body, 'Branches' ), undefined );
	} );

	test( 'falls back to the PR alone when the base has no coverage', () => {
		const body = renderCoverageSection( { head, base: { php: {} }, floors: FLOORS } );
		assert.match( body, /No coverage data for the base branch/ );
		assert.equal( row( body, 'Lines' ), '| Lines | 95% | 90% ✅ |' );
	} );

	test( 'returns an empty string when nothing was measured', () => {
		assert.equal( renderCoverageSection( { head: { php: {} }, base: { php: {} }, floors: FLOORS } ), '' );
	} );

	test( 'throws on a misspelled metric or an unknown suite', () => {
		assert.throws( () => renderCoverageSection( { head, base, floors: { php: { lnes: 90 } } } ), /not a metric this suite reports/ );
		assert.throws( () => renderCoverageSection( { head, base, floors: { js: { lines: 90 } } } ), /not a coverage suite/ );
	} );

	test( 'accepts the real quality-thresholds.json', () => {
		const { coverage } = JSON.parse( fs.readFileSync( path.resolve( __dirname, '../../quality-thresholds.json' ), 'utf8' ) );
		assert.doesNotThrow( () => renderCoverageSection( { head, base, floors: coverage } ) );
	} );
} );

describe( 'renderPrReport', () => {
	const quality = { phpcs: { outcome: 'success' }, phpstan: { outcome: 'success' }, phpmd: { outcome: 'success' } };
	const metrics = metricsInput( { Violations: { error: 0 } }, { Violations: { error: 0 } } );
	const coverage = {
		head: { php: { lines: metric( 95, 100, 95 ) } },
		base: { php: { lines: metric( 95, 100, 95 ) } },
		floors: FLOORS,
	};
	const render = ( extra = {} ) => renderPrReport( { quality, metrics, coverage, headSha: HEAD_SHA, baseSha: BASE_SHA, runUrl: RUN_URL, ...extra } );

	test( 'orders heading, SHA line, quality, metrics, coverage and footer', () => {
		const body = render();
		const at = ( text ) => body.indexOf( text );
		assert.ok( body.startsWith( '## 📊 PR Report\n\n`bbbbbbb` (base) → `aaaaaaa` (PR)' ) );
		assert.ok( at( '### PHP code quality' ) < at( '### Code Metrics' ) );
		assert.ok( at( '### Code Metrics' ) < at( '### Coverage' ) );
		assert.ok( at( '### Coverage' ) < at( RUN_URL ) );
		assert.match( body, /<sub>.*\[the CI run\]\(https:\/\/github\.com\/o\/r\/actions\/runs\/1\)/ );
	} );

	test( 'omits each section when its data is absent', () => {
		const body = render( { quality: null, coverage: null } );
		assert.doesNotMatch( body, /PHP code quality/ );
		assert.doesNotMatch( body, /### Coverage/ );
		assert.match( body, /### Code Metrics/ );
		assert.doesNotMatch( render( { metrics: null } ), /### Code Metrics/ );
	} );

	test( 'notes skipped tests only when Quality failed', () => {
		assert.match( render( { qualityResult: 'failure', testsResult: 'skipped' } ), /Tests skipped because Quality failed\./ );
		assert.doesNotMatch( render( { qualityResult: 'success', testsResult: 'success' } ), /Tests skipped/ );
		assert.doesNotMatch( render( { qualityResult: 'cancelled', testsResult: 'skipped' } ), /Tests skipped/ );
	} );
} );

describe( 'the CLI', () => {
	const CLOVER = '<coverage><project><metrics files="1" statements="100" coveredstatements="95" methods="10" coveredmethods="9"/></project></coverage>';

	const setup = () => {
		const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'report-' ) );
		const write = ( file, content ) => {
			fs.mkdirSync( path.dirname( path.join( dir, file ) ), { recursive: true } );
			fs.writeFileSync( path.join( dir, file ), typeof content === 'string' ? content : JSON.stringify( content ) );
		};
		const thresholds = path.join( dir, 't.json' );
		fs.writeFileSync( thresholds, JSON.stringify( { coverage: { php: { lines: 90, methods: 90 } }, metrics: { max: MAX, gate: [ 'critical' ] } } ) );
		const run = ( env = {} ) => {
			const output = path.join( dir, 'out.md' );
			const result = spawnSync( 'node', [ SCRIPT ], {
				env: { ...process.env, REPORTS_DIR: path.join( dir, 'reports' ), THRESHOLDS: thresholds, OUTPUT: output, HEAD_SHA, BASE_SHA, RUN_URL, ...env },
				encoding: 'utf8',
			} );
			return { result, body: fs.existsSync( output ) ? fs.readFileSync( output, 'utf8' ) : null };
		};
		return { write, run };
	};

	test( 'renders every section from the report-* artifact directories', () => {
		const { write, run } = setup();
		write( 'reports/report-quality/quality.json', { phpcs: { outcome: 'success' }, phpstan: { outcome: 'failure' }, phpmd: { outcome: 'success' } } );
		write( 'reports/report-metrics/metrics-summary.json', { OOP: { classes: 4 }, Violations: { critical: 0, error: 1 } } );
		write( 'reports/report-metrics/base-summary.json', { OOP: { classes: 4 }, Violations: { critical: 0, error: 1 } } );
		write( 'reports/report-coverage/php-clover.xml', CLOVER );
		const { result, body } = run( { QUALITY_RESULT: 'failure', TESTS_RESULT: 'skipped' } );
		assert.equal( result.status, 0 );
		assert.match( body, /\| PHPStan \| ❌ \|/ );
		assert.match( body, /\| Violations: error \| 1 \| 1 \| — \| 2 ✅ \|/ );
		assert.match( body, /\| Lines \| 95% \| 90% ✅ \|/ );
		assert.match( body, /Tests skipped because Quality failed\./ );
	} );

	test( 'omits sections whose artifacts are absent', () => {
		const { write, run } = setup();
		write( 'reports/report-quality/quality.json', { phpcs: { outcome: 'success' } } );
		const { body } = run();
		assert.match( body, /### PHP code quality/ );
		assert.doesNotMatch( body, /Code Metrics|### Coverage/ );
	} );

	test( 'still renders with no reports directory at all', () => {
		const { result, body } = setup().run();
		assert.equal( result.status, 0 );
		assert.match( body, /^## 📊 PR Report/ );
	} );

	test( 'fails loudly when the workflow forgets to pass a SHA', () => {
		const { result } = setup().run( { HEAD_SHA: '' } );
		assert.equal( result.status, 1 );
		assert.match( result.stderr, /::error::HEAD_SHA is not set/ );
	} );
} );
