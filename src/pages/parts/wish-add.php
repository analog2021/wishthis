<?php

/**
 * @author Jay Trees <github.jay@grandel.anonaddy.me>
 */

namespace wishthis;

?>

<div class="ui secondary menu wish-add">
    <a class="item active"   data-tab="general"><?= __('General') ?></a>
    <a class="item disabled" data-tab="product"><?= __('Product') ?></a>
</div>

<div class="ui tab active" data-tab="general">
    <div class="field">
        <label>
            <?= __('Link to the product') ?>
            <i class="orange linkify icon link affiliate" data-content="<?= __('This is an affiliate link.') ?>"></i>
        </label>

        <input type       ="url"
               name       ="wish_url"
               maxlength  ="255"
               placeholder="https://"
        />
    </div>

    <div class="ui icon message wish-lookup" style="display: none"></div>

    <div class="ui two column grid">
        <div class="stackable row">
            <div class="column">

                <div class="field">
                    <label><?= __('Title') ?></label>

                    <div class="ui input">
                        <input type           ="text"
                               name           ="wish_title"
                               data-max-length="<?= Wish::TITLE_MAX_LENGTH ?>"
                        />
                    </div>

                    <div class="wish-title-length" style="display: none"></div>
                </div>

                <div class="field">
                    <label><?= __('Description') ?></label>

                    <div class="ui input">
                        <textarea name="wish_description"></textarea>
                    </div>
                </div>

            </div>

            <div class="column">

                <div class="field">
                    <label><?= __('Priority') ?></label>

                    <select class="ui selection clearable dropdown priority"
                            name ="wish_priority"
                    >
                        <option value=""><?= __('Select priority') ?></option>

                        <?php foreach (Wish::$priorities as $priority => $item) { ?>
                            <option value="<?= $priority ?>"><?= $item['name'] ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field">
                    <label><?= __('Image') ?></label>

                    <input type     ="url"
                           name     ="wish_image"
                           maxlength="255"
                    />

                    <img class="ui small rounded image wish-image-preview" alt="" style="display: none" />
                </div>

                <div class="grouped fields">
                    <label><?= __('Properties') ?></label>

                    <div class="field">
                        <div class="ui checkbox wish-is-purchasable">

                            <input type="checkbox"
                                   name="wish_is_purchasable"
                            />
                            <label><?= __('Is purchasable') ?></label>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="ui tab" data-tab="product">
    <div class="ui two column grid">
        <div class="stackable row">
            <div class="column">

                <div class="field">
                    <label><?= __('Price') ?></label>

                    <input type     ="number"
                           step     ="0.01"
                           name     ="wish_price"
                           maxlength="9"
                    />
                </div>

            </div>
        </div>
    </div>
</div>
