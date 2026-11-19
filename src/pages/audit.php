<?php
/*
 * FUNCTION AND CUSTOMIZATION (developer guide):
 * Displays recent activity records for owners. Customize table headings and displayed
 * history limit here; logging itself is handled elsewhere.
 */
/**
 * src/pages/audit.php — Activity log page template (owner only).
 *
 * Read-only table showing the 100 most recent logged actions.
 * Records are written by logit() in config.php whenever a significant
 * action is performed anywhere in the system.
 *
 * Variables available: $pdo
 */
?>

<section class="panel">
    <table>
        <thead>
            <tr>
                <th>WHEN</th>
                <th>USER</th>
                <th>ACTION</th>
                <th>DETAILS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (getall($pdo,
                'SELECT l.*, u.user_name
                 FROM   audit_logs l
                 JOIN   users      u ON u.user_id = l.user_id
                 ORDER  BY l.log_id DESC
                 LIMIT  100') as $r): ?>
                <tr>
                    <td><?= h($r['created_at']) ?></td>
                    <td><?= h($r['user_name']) ?></td>
                    <td><?= h($r['action']) ?></td>
                    <td><?= h($r['details']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
