<?php
/**
 * Official-calendar golden fixtures for G-008.
 *
 * Authority identity:
 * University of Tehran, Institute of Geophysics, Calendar Center,
 * "Official Calendar of the Country, Solar Hijri year 1405".
 * Official PDF: https://calendar.ut.ac.ir/documents/2139738/7092644/Calendar-1405.pdf
 * University announcement: https://t.me/s/PRGeophysics?before=5059
 *
 * The official endpoint redirected in the implementation environment; the
 * listed calendar rows were cross-read from an indexed copy carrying the same
 * final-calendar title and University of Tehran Calendar Center attribution.
 */

return array(
	'authority' => array(
		'name'         => 'University of Tehran Institute of Geophysics Calendar Center',
		'year'         => 1405,
		'official_pdf' => 'https://calendar.ut.ac.ir/documents/2139738/7092644/Calendar-1405.pdf',
	),
	'cases'     => array(
		'nowruz'            => array( '2026-03-21', '1405-01-01' ),
		'farvardin_end'     => array( '2026-04-20', '1405-01-31' ),
		'ordibehesht_start' => array( '2026-04-21', '1405-02-01' ),
		'khordad_start'     => array( '2026-05-22', '1405-03-01' ),
		'tir_start'         => array( '2026-06-22', '1405-04-01' ),
		'mordad_start'      => array( '2026-07-23', '1405-05-01' ),
		'shahrivar_start'   => array( '2026-08-23', '1405-06-01' ),
		'mehr_start'        => array( '2026-09-23', '1405-07-01' ),
		'aban_start'        => array( '2026-10-23', '1405-08-01' ),
		'dey_start'         => array( '2026-12-22', '1405-10-01' ),
		'bahman_start'      => array( '2027-01-21', '1405-11-01' ),
		'esfand_start'      => array( '2027-02-20', '1405-12-01' ),
		'year_end'          => array( '2027-03-20', '1405-12-29' ),
	),
);
