<table class="widefat fixed">
  <thead>
  <tr>
    <th class="manage-column column-cb">Time</th>
    <th class="manage-column column-cb">Content</th>
  </tr>
  </thead>

  <tbody>
    <?php foreach($logs as $index => $log): ?>
      <tr <?php if ($index % 2 == 0): ?>class="alternate"<?php endif; ?>>
        <td class="column-columnname"><?php echo $log->post_date ?></td>
        <td class="column-columnname"><?php echo $log->post_content ?></td>
      </tr>
    <?php endforeach ?>
  </tbody>
</table>
