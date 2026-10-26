<div class="admin-chart-grid">
    <?php foreach ([
        'Requests by blood type' => $bloodBreakdown,
        'Requests by location' => $cityBreakdown,
        'Request status' => $statusBreakdown,
        'Member registrations by month' => $registrationTrend,
        'Community reports by month' => $reportTrend
    ] as $chartTitle => $chartRows): ?>
        <section class="admin-panel admin-chart-panel">
            <h2><?= admin_h($chartTitle) ?></h2>
            <?php if (!$chartRows): ?>
                <p class="empty-state">No records available yet.</p>
            <?php else: ?>
                <?php $chartMax = max(array_map(static function ($row) { return (int) $row['total']; }, $chartRows)); ?>
                <ul class="admin-bar-list">
                    <?php foreach ($chartRows as $chartRow): ?>
                        <?php $barWidth = $chartMax > 0 ? max(3, (int) round(((int) $chartRow['total'] / $chartMax) * 100)) : 0; ?>
                        <li>
                            <span><?= admin_h($chartRow['label'] ?: 'Unspecified') ?></span>
                            <span class="admin-bar-track"><span style="width:<?= $barWidth ?>%"></span></span>
                            <strong><?= (int) $chartRow['total'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
