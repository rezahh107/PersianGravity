#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$adminPath = $root . '/admin/class-pgr-product-admin.php';
$cssPath = $root . '/assets/css/pgr-admin.css';

$admin = file_get_contents($adminPath);
if ($admin === false) {
    fwrite(STDERR, "Cannot read product admin.\n");
    exit(1);
}

$old = <<<'PHP'
				$this->status_card( __( 'Profile Registry', 'persian-gravityforms' ), $profile_count, 'AVAILABLE' );
				?>
			</div>
		</div>
		<?php
	}

	/** Render local, version-controlled bilingual Help Center. */
PHP;

$new = <<<'PHP'
				$this->status_card( __( 'Profile Registry', 'persian-gravityforms' ), $profile_count, 'AVAILABLE' );
				?>
			</div>
			<?php $this->render_gravity_flow_compatibility_status(); ?>
		</div>
		<?php
	}

	/** Render reporting-only Gravity Flow compatibility diagnostics. */
	public function render_gravity_flow_compatibility_status() {
		if ( ! class_exists( 'PGR_Gravity_Flow_Compatibility_Diagnostics', false ) ) {
			return;
		}

		$capabilities = PGR_Gravity_Flow_Compatibility_Diagnostics::capabilities();
		$snapshot     = PGR_Gravity_Flow_Compatibility_Diagnostics::status_snapshot();
		?>
		<section class="pgr-panel pgr-compatibility-status">
			<h2><?php echo esc_html__( 'Gravity Flow compatibility diagnostics', 'persian-gravityforms' ); ?></h2>
			<p class="pgr-status-value"><?php echo esc_html__( 'Informational maintenance evidence only. These observations do not enable or disable compatibility.', 'persian-gravityforms' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Capability', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'State', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Reason', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Observed Gravity Flow', 'persian-gravityforms' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Last observed', 'persian-gravityforms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $capabilities as $capability_id => $label ) : ?>
						<?php
						$record      = $snapshot[ $capability_id ];
						$state       = (string) $record['state'];
						$reason_id   = (string) $record['reason_id'];
						$summary     = (string) $record['summary'];
						$host        = is_string( $record['host_version'] ) && '' !== $record['host_version'] ? $record['host_version'] : __( 'Not observed', 'persian-gravityforms' );
						$observed_at = is_int( $record['observed_at'] ) ? gmdate( 'Y-m-d H:i:s \\U\\T\\C', $record['observed_at'] ) : __( 'Not observed', 'persian-gravityforms' );
						?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong><br><code class="pgr-ltr"><?php echo esc_html( $capability_id ); ?></code></td>
							<td><span class="pgr-badge" data-state="<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state ); ?></span></td>
							<td><?php echo esc_html( $summary ); ?><br><code class="pgr-ltr"><?php echo esc_html( $reason_id ); ?></code></td>
							<td><code class="pgr-ltr"><?php echo esc_html( $host ); ?></code></td>
							<td><code class="pgr-ltr"><?php echo esc_html( $observed_at ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/** Render local, version-controlled bilingual Help Center. */
PHP;

if (substr_count($admin, $old) !== 1) {
    fwrite(STDERR, "Product-admin patch anchor drifted.\n");
    exit(1);
}
$admin = str_replace($old, $new, $admin);
file_put_contents($adminPath, $admin);

$css = file_get_contents($cssPath);
if ($css === false) {
    fwrite(STDERR, "Cannot read admin CSS.\n");
    exit(1);
}
$oldCss = <<<'CSS'
.pgr-badge[data-state="WARNING"] {
	background: #fff8e5;
}

.pgr-badge[data-state="UNAVAILABLE"],
.pgr-badge[data-state="DISABLED"] {
	background: #f6f7f7;
	color: #646970;
}
CSS;
$newCss = <<<'CSS'
.pgr-badge[data-state="WARNING"],
.pgr-badge[data-state="DEGRADED"] {
	background: #fff8e5;
}

.pgr-badge[data-state="UNAVAILABLE"],
.pgr-badge[data-state="NOT_EVALUATED"],
.pgr-badge[data-state="DISABLED"] {
	background: #f6f7f7;
	color: #646970;
}
CSS;
if (substr_count($css, $oldCss) !== 1) {
    fwrite(STDERR, "Admin CSS patch anchor drifted.\n");
    exit(1);
}
$css = str_replace($oldCss, $newCss, $css);
file_put_contents($cssPath, $css);
