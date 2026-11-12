<?php

namespace Drupal\bootstrap_ui_kit\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Separate form to manage UI Kit Glossary groups and sections.
 */
class BootstrapUiKitGlossaryForm extends ConfigFormBase {

  const SETTINGS = 'bootstrap_ui_kit.settings';

  public function getFormId(): string {
    return 'bootstrap_ui_kit_glossary_form';
  }

  protected function getEditableConfigNames(): array {
    return [ static::SETTINGS ];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::SETTINGS);

    // Messages container.
    $form['messages'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'glossary-messages'],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#attributes' => ['id' => 'glossary-unified-table'],
      '#header' => [ '', $this->t('Title'), $this->t('Key'), $this->t('Type'), $this->t('Enable modal?'), [ 'data' => $this->t('Weight'), 'class' => ['tabledrag-hide'] ], $this->t('Actions') ],
      '#tabledrag' => [
        // Use standard Drupal tabledrag with hidden inputs for reliability
        [ 'action' => 'match', 'relationship' => 'parent', 'group' => 'glossary-parent', 'subgroup' => 'glossary-parent', 'source' => 'glossary-id' ],
        [ 'action' => 'order', 'relationship' => 'sibling', 'group' => 'glossary-weight' ],
      ],
      '#empty' => $this->t('No sections yet. Use the blank rows to add groups and sections.'),
      '#tree' => TRUE,
      '#caption' => $this->t('Glossary groups and sections (drag to reorder).'),
    ];

    $glossary = $form_state->get('glossary_current') ?? $this->normalizeGlossaryStructure($config->get('glossary') ?? []);
    $glossary = $this->sortGroupsByWeight($glossary);
    $form_state->set('glossary_current', $glossary);

    foreach ($glossary as $group_key => $group) {
      $row_id = 'group_' . $group_key;
      $row =& $form['table'][$row_id];

      // Drag handle cell with standard markup so Drupal tabledrag JS can attach behavior.
      $row['drag'] = ['#type' => 'markup', '#markup' => ''];

      // Title field - direct textfield for tabledrag compatibility
      $row['title'] = [
        '#type' => 'textfield',
        '#default_value' => $group['title'] ?? ucfirst($group_key),
        '#size' => 32,
        '#attributes' => [
          'class' => ['glossary-group-title'],
          'style' => 'font-weight:700;'
        ],
      ];

      // Key column contains hidden metadata AND visible key display
      $row['key'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['glossary-key-container']],
        // Hidden metadata fields for form processing
        'id' => ['#type' => 'hidden', '#value' => $row_id, '#attributes' => ['class' => ['glossary-id']]],
        'parent' => ['#type' => 'hidden', '#value' => '0', '#attributes' => ['class' => ['glossary-parent']]],
        'item_type' => ['#type' => 'hidden', '#value' => 'group'],
        'group_key' => ['#type' => 'value', '#value' => $group_key],
        'section_key' => ['#type' => 'value', '#value' => ''],
        // Visible key display
        'display' => ['#type' => 'item', '#markup' => '<strong><code>'.$group_key.'</code></strong>'],
      ];

      $row['type'] = ['#type'=>'item','#markup'=>'<strong>🗂️ '.$this->t('Group').'</strong>'];
      $row['modal'] = ['#markup'=>'—'];
      $row['weight'] = [ '#type'=>'weight','#default_value'=>(int)($group['weight']??0),'#title'=>$this->t('Weight'),'#title_display'=>'invisible','#attributes'=>['class'=>['glossary-weight']], '#wrapper_attributes'=>['class'=>['tabledrag-hide']] ];
      $row['actions'] = [ '#type'=>'container', '#attributes'=>['class'=>['glossary-ops']],
        // DECOY: Button that opens modal confirmation (no actual form submission)
        'remove_modal'=>[
          '#type'=>'button','#value'=>$this->t('Remove Group'),
          '#name' => 'open_remove_confirm_' . $group_key,
          '#ajax'=>[
            'callback'=>'::openGroupRemovalConfirmation',
            'wrapper'=>'glossary-form-wrapper',
            'event' => 'click',
            'progress' => ['type' => 'none'],
            'disable-refocus' => TRUE,
          ],
          '#attributes'=>[
            'class'=>['btn','btn-sm','btn-danger','button--danger'],
            'data-group-key' => $group_key,
            'data-section-count' => count($group['sections'] ?? []),
          ],
          'group_key' => ['#type' => 'value', '#value' => $group_key],
        ],

        // WORKING: Hidden direct removal button (will be triggered by JS after modal confirmation)
        'remove_direct'=>[
          '#type'=>'submit','#value'=>$this->t('Remove Group Direct'),
          '#name' => 'remove_group_direct_' . $group_key,
          '#submit'=>['::removeGroupDirectly'],
          '#ajax'=>[
            'callback'=>'::ajaxGlossaryCallback',
            'wrapper'=>'glossary-form-wrapper',
            'progress' => ['type' => 'none'],
          ],
          '#limit_validation_errors'=>[['table']],
          '#attributes'=>[
            'class'=>['btn','btn-sm','btn-danger','button--danger','hidden-direct-remove'],
            'style' => 'display: none;', // Hidden from UI
            'data-group-key' => $group_key,
          ],
          'group_key' => ['#type' => 'value', '#value' => $group_key],
        ],
      ];

      // Add draggable + depth class to the row
      $row['#attributes']['class'][] = 'draggable';
      $row['#attributes']['class'][] = 'tabledrag-depth-0';

      foreach ($this->stableSortSections($group['sections']) as $section_key => $section) {
        $s_row_id = 'section_' . $group_key . '_' . $section_key;
        $srow =& $form['table'][$s_row_id];
        $srow['drag'] = ['#type' => 'markup', '#markup' => ''];

        // Title field - direct textfield for tabledrag compatibility
        $srow['title'] = [
          '#type' => 'textfield',
          '#default_value' => $section['title'] ?? ucfirst(str_replace('_', ' ', $section_key)),
          '#size' => 32,
          '#attributes' => [ 'style' => 'margin-left:20px;' ],
        ];

        // Key column contains hidden metadata AND visible key display
        $srow['key'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['glossary-key-container']],
          // Hidden metadata fields for form processing
          'id' => ['#type' => 'hidden', '#value' => $s_row_id, '#attributes' => ['class' => ['glossary-id']]],
          'parent' => ['#type' => 'hidden', '#value' => $row_id, '#attributes' => ['class' => ['glossary-parent']]],
          'item_type' => ['#type' => 'hidden', '#value' => 'section'],
          'group_key' => ['#type' => 'value', '#value' => $group_key],
          'section_key' => ['#type' => 'value', '#value' => $section_key],
          // Visible key display
          'display' => ['#type' => 'item', '#markup' => '<span><code>'.$section_key.'</code></span>'],
        ];

        $srow['type'] = ['#type'=>'item','#markup'=>'<span>📄 '.$this->t('Section').'</span>'];
        $srow['modal'] = ['#type' => 'checkbox', '#default_value' => !empty($section['modal'])];
        $srow['weight'] = [ '#type'=>'weight','#default_value'=>(int)($section['weight']??0),'#title'=>$this->t('Weight'),'#title_display'=>'invisible','#attributes'=>['class'=>['glossary-weight']], '#wrapper_attributes'=>['class'=>['tabledrag-hide']] ];
        $srow['actions'] = [ '#type'=>'container','#attributes'=>['class'=>['glossary-ops']], 'remove'=>[
          '#type'=>'submit','#value'=>$this->t('Remove'),'#name'=>'remove_section_'.$group_key.'_'.$section_key,
          '#submit'=>['::removeItemSubmit'],'#ajax'=>['callback'=>'::ajaxGlossaryCallback','wrapper'=>'glossary-form-wrapper'],
          '#limit_validation_errors'=>[['table']],
          '#attributes'=>['class'=>['btn','btn-sm','btn-danger']],
          'group_key' => ['#type' => 'value', '#value' => $group_key],
          'section_key' => ['#type' => 'value', '#value' => $section_key],
        ] ];

        // Add draggable + depth class to the section row
        $srow['#attributes']['class'][] = 'draggable';
        $srow['#attributes']['class'][] = 'tabledrag-depth-1';
      }
    }

    // New group blank row.
    $blank_group =& $form['table']['__new_group'];
    $blank_group['drag'] = ['#type' => 'markup', '#markup' => ''];
    $blank_group['title'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#placeholder' => $this->t('Enter new group name'),
      '#attributes' => ['style' => 'font-weight:700;'],
    ];

    // Key column with hidden metadata for new group
    $blank_group['key'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['glossary-key-container']],
      // Hidden metadata fields
      'item_type' => ['#type' => 'hidden', '#value' => 'group'],
      'group_key' => ['#type' => 'value', '#value' => ''],
      'section_key' => ['#type' => 'hidden', '#value' => ''],
      // Visible display
      'display' => ['#type' => 'item', '#markup' => '<em>'.$this->t('Will generate').'</em>'],
    ];

    $blank_group['type'] = ['#type'=>'item','#markup'=>'<em>'.$this->t('New Group').'</em>'];
    $blank_group['modal'] = ['#markup'=>'—'];
    $blank_group['weight'] = [ '#type'=>'weight','#default_value'=>0,'#title'=>$this->t('Weight'),'#title_display'=>'invisible','#attributes'=>['class'=>['glossary-weight']], '#wrapper_attributes'=>['class'=>['tabledrag-hide']] ];
    $blank_group['actions'] = ['#markup'=>'<span class="text-muted">—</span>'];

    // New section blank row.
    $blank_section =& $form['table']['__new_section'];
    $blank_section['drag'] = ['#type' => 'markup', '#markup' => ''];
    $blank_section['title'] = [
      '#type' => 'textfield',
      '#default_value' => '',
      '#placeholder' => $this->t('Enter new section title'),
      '#attributes' => ['style' => 'margin-left:20px;'],
    ];

    // Key column with hidden metadata for new section
    $blank_section['key'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['glossary-key-container']],
      // Hidden metadata fields
      'item_type' => ['#type' => 'hidden', '#value' => 'section'],
      'group_key' => ['#type' => 'hidden', '#value' => ''],
      'section_key' => ['#type' => 'hidden', '#value' => ''],
      // Visible display
      'display' => ['#type' => 'item', '#markup' => '<em>'.$this->t('Will generate').'</em>'],
    ];

    // Provide placeholder cells so column count matches header.
    $blank_section['type'] = ['#type' => 'item', '#markup' => '<em>'.$this->t('New Section').'</em>'];
    $blank_section['modal'] = ['#type' => 'checkbox', '#default_value' => FALSE];
    $blank_section['weight'] = [ '#type' => 'weight', '#default_value' => 0, '#title' => $this->t('Weight'), '#title_display' => 'invisible', '#attributes' => ['class' => ['glossary-weight']], '#wrapper_attributes' => ['class' => ['tabledrag-hide']] ];
    $group_options = ['' => $this->t('- Select Group -')];
    foreach ($glossary as $gk => $gdata) {
      $group_options[$gk] = isset($gdata['title']) && $gdata['title'] !== '' ? $gdata['title'] : ucfirst($gk);
    }
    $blank_section['actions'] = [ '#type' => 'container', 'group_select' => ['#type' => 'select', '#options' => $group_options, '#default_value' => '', '#title' => $this->t('Parent Group'), '#title_display' => 'invisible'] ];

    // Wrap everything in a container for AJAX updates
    $form['#prefix'] = '<div id="glossary-form-wrapper">';
    $form['#suffix'] = '</div>';

    // Attach the glossary form JavaScript library
    $form['#attached']['library'][] = 'bootstrap_ui_kit/bootstrap-ui-kit.glossary-form';

    return parent::buildForm($form,$form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $table_values = $values['table'] ?? [];
    $reconstructed = $this->reconstructFromTableValues($table_values, TRUE);
    $existing = $this->config(static::SETTINGS)->get('glossary') ?? [];
    $glossary = empty($reconstructed) && !empty($existing) ? $existing : $reconstructed;

    $this->configFactory()->getEditable(static::SETTINGS)->set('glossary', $glossary)->save();
    parent::submitForm($form, $form_state);
  }

  public function ajaxGlossaryCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  // TEMPORARY: Direct group removal method (bypasses modal)
  public function removeGroupDirectly(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $group_key = $trigger['group_key']['#value'] ?? '';

    if (!$group_key) {
      \Drupal::messenger()->addError($this->t('Unable to determine which group to remove.'));
      return;
    }

    $glossary = $form_state->get('glossary_current') ?? [];
    if (isset($glossary[$group_key])) {
      $section_count = count($glossary[$group_key]['sections'] ?? []);
      unset($glossary[$group_key]);

      if ($section_count > 0) {
        \Drupal::messenger()->addMessage($this->t('Removed group "@group" and @count sections.', [
          '@group' => $group_key,
          '@count' => $section_count
        ]));
      } else {
        \Drupal::messenger()->addMessage($this->t('Removed group "@group".', ['@group' => $group_key]));
      }

      $form_state->set('glossary_current', $glossary);
      $this->configFactory()->getEditable(static::SETTINGS)->set('glossary', $glossary)->save();
      $form_state->setRebuild(TRUE);
    }
    else {
      \Drupal::messenger()->addError($this->t('Group "@group" not found.', ['@group' => $group_key]));
    }
  }

  public function openGroupRemovalConfirmation(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $trigger = $form_state->getTriggeringElement();
    $group_key = $trigger['group_key']['#value'] ?? ($trigger['#attributes']['data-group-key'] ?? '');
    if ($group_key === '') { return $response; }
    $glossary = $form_state->get('glossary_current') ?? [];
    $sections = $glossary[$group_key]['sections'] ?? [];
    $message = $this->getGroupRemovalConfirmationMessage($group_key, $sections);

    // IMPORTANT: Do NOT add jQuery UI dialog pane classes here, or the dialog API
    // will clone/create a second button pane and strip custom attributes.
    $dialog_content = [
      '#type' => 'container',
      'message' => [
        '#type' => 'markup',
        '#markup' => $message,
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['glossary-remove-group-actions']],
        'cancel' => [
          '#type' => 'button',
          '#value' => $this->t('Cancel'),
          '#attributes' => [
            'class' => ['btn','btn-secondary','glossary-cancel-remove'],
            'data-group-key' => $group_key,
          ],
        ],
        'confirm' => [
          '#type' => 'button',
          '#value' => $this->t('Remove Group'),
          '#attributes' => [
            'class' => ['btn','btn-danger','button--danger','align-right','glossary-confirm-remove'],
            'data-group-key' => $group_key,
          ],
        ],
      ],
      '#attached' => ['library' => ['bootstrap_ui_kit/bootstrap-ui-kit.glossary-form','core/drupal.dialog.ajax']]
    ];

    $response->addCommand(new OpenModalDialogCommand($this->t('Confirm Group Removal'), $dialog_content, ['width' => '500']));
    return $response;
  }


  public function removeItemSubmit(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $group_key = $trigger['group_key']['#value'] ?? '';
    $section_key = $trigger['section_key']['#value'] ?? '';
    $glossary = $form_state->get('glossary_current') ?? [];
    if ($group_key && $section_key && isset($glossary[$group_key]['sections'][$section_key])) {
      unset($glossary[$group_key]['sections'][$section_key]);
      \Drupal::messenger()->addMessage($this->t('Removed section "@section" from group "@group".', ['@section'=>$section_key,'@group'=>$group_key]));
    }
    else {
      \Drupal::messenger()->addError($this->t('Unable to remove section.'));
    }
    $form_state->set('glossary_current', $glossary);
    $this->configFactory()->getEditable(static::SETTINGS)->set('glossary', $glossary)->save();
    $form_state->setRebuild(TRUE);
  }

  protected function reconstructFromTableValues(array $rows, bool $include_new): array {
    if (!$rows) return [];
    $by_id = [];
    foreach ($rows as $rk=>$row) {
      if (!$include_new && str_starts_with($rk,'__new_')) continue;
      if (!is_array($row)) continue;
      $id = $row['key']['id'] ?? $rk; $parent = (string)($row['key']['parent'] ?? '0'); $item_type=$row['key']['item_type']??''; $group_key=$row['key']['group_key']??''; $section_key=$row['key']['section_key']??''; $weight=(int)($row['weight']??0); $modal=isset($row['modal'])?(bool)$row['modal']:FALSE; $title=$row['title']??''; if(is_array($title)){$title=$title['#value']??'';} $by_id[$id]=['id'=>$id,'parent'=>$parent,'item_type'=>$item_type,'group_key'=>$group_key,'section_key'=>$section_key,'weight'=>$weight,'title_text'=>trim((string)$title),'modal'=>$modal];
    }
    $groups=[]; $rowIdByGroupKey=[];
    foreach ($by_id as $id=>$r) if($r['item_type']==='group' && $r['parent']==='0' && $r['group_key']!=='') { $gk=$r['group_key']; $groups[$id]=['key'=>$gk,'title'=>$r['title_text']?:ucfirst($gk),'weight'=>$r['weight'],'sections'=>[]]; $rowIdByGroupKey[$gk]=$id; }
    if ($include_new && !empty($rows['__new_group']['title'])) { $ng=trim((string)$rows['__new_group']['title']); if($ng!==''){ $existing=array_map(static fn($g)=>$g['key'],$groups); $mk=$this->generateMachineName($ng,$existing); $gid='group_'.$mk; $groups[$gid]=['key'=>$mk,'title'=>$ng,'weight'=>99,'sections'=>[]]; $rowIdByGroupKey[$mk]=$gid; }}
    foreach ($by_id as $id=>$r) if($r['item_type']==='section'){ $parent_row=$r['parent']; if($parent_row==='0'||!isset($groups[$parent_row])){ $inf='group_'.$r['group_key']; if(isset($groups[$inf])) $parent_row=$inf; } if(!isset($groups[$parent_row])) continue; $sk=$r['section_key']; if($sk==='') continue; $title=$r['title_text']?:ucfirst(str_replace('_',' ',$sk)); $groups[$parent_row]['sections'][$sk]=['title'=>$title,'modal'=>$r['modal'],'weight'=>$r['weight']]; }
    if ($include_new && !empty($rows['__new_section']['title'])) { $ns=trim((string)$rows['__new_section']['title']); $pg=$rows['__new_section']['actions']['group_select']??''; if($ns!=='' && $pg!=='' && isset($rowIdByGroupKey[$pg])) { $parentRowId=$rowIdByGroupKey[$pg]; $existingSec=array_keys($groups[$parentRowId]['sections']); $mk=$this->generateMachineName($ns,$existingSec); $groups[$parentRowId]['sections'][$mk]=['title'=>$ns,'modal'=>!empty($rows['__new_section']['modal']),'weight'=>0]; }}
    $out=[]; foreach ($groups as $g){ $out[$g['key']]=['title'=>$g['title'],'weight'=>$g['weight'],'sections'=>$g['sections']]; uasort($out[$g['key']]['sections'], static fn($a,$b)=>($a['weight']??0)<=>($b['weight']??0)); } uasort($out, static fn($a,$b)=>($a['weight']??0)<=>($b['weight']??0)); return $out; }

  protected function normalizeGlossaryStructure(array $glossary): array { $n=[]; foreach($glossary as $gk=>$d){ $n[$gk]=[ 'title'=>$d['title']??ucfirst($gk), 'weight' => (int)($d['weight']??0), 'sections'=>is_array($d['sections']??null)?$d['sections']:[] ]; } return $n; }
  protected function sortGroupsByWeight(array $glossary): array { uasort($glossary, static fn($a,$b)=>(($a['weight']??0)<=>($b['weight']??0))); return $glossary; }
  protected function stableSortSections(array $sections): array { uasort($sections, static fn($a,$b)=>(($a['weight']??0)<=>($b['weight']??0))); return $sections; }
  protected function generateMachineName(string $label, array $existing): string { $base=preg_replace('/[^a-z0-9]+/i','_',$label); $base=preg_replace('/_+/','_',$base); $base=strtolower(trim($base,'_'))?:'item'; $exist=array_fill_keys($existing,TRUE); $name=$base; $i=1; while(isset($exist[$name])) $name=$base.'_'.$i++; return $name; }
  private function getGroupRemovalConfirmationMessage(string $group_key, array $sections): string { $c=count($sections); if($c===0) return '<p>'.$this->t('Are you sure you want to remove the group "@group"?', ['@group'=>$group_key]).'</p>'; if($c===1) return '<p>'.$this->t('The group "@group" has one section. Removing the group will also remove this section.', ['@group'=>$group_key]).'</p><p>'.$this->t('Are you sure you want to proceed?').'</p>'; return '<p>'.$this->t('The group "@group" has @c sections. Removing the group will also remove these sections.', ['@group'=>$group_key,'@c'=>$c]).'</p><p>'.$this->t('Are you sure you want to proceed?').'</p>'; }
}
