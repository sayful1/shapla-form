<template>
    <div class="dialog-contact-form--edit-form">
        <h1 class="wp-heading-inline">Edit Form: {{ title }}</h1>
        <a href="#" class="page-title-action" @click.prevent="backToForms">Back to Forms</a>
        <hr class="wp-header-end">
        <tabs>
            <tab name="Form Fields" :selected="true">
                <columns>
                    <column :tablet="8">
                        <draggable :list="fields" handle=".sort-field" class="shapla-columns is-multiline">
                            <column :class="`${field.field_width}`" v-for="field in fields" :key="field.field_id">
                                <field
                                        :field="field"
                                        :active="field === activeField"
                                        @click:action="handleFieldAction"
                                ></field>
                            </column>
                        </draggable>
                    </column>
                    <column>
                        <tabs :fullwidth="true" @tab:change="onTabChange">
                            <tab name="Available Fields" :selected="!showFieldOption">
                                <columns class="dcf-available-fields" :multiline="true" :gapless="false" mobile>
                                    <column :mobile="6" :tablet="6" v-for="_field in formFields" :key="_field.id">
                                        <div class="dcf-available-field" @click="addNewField(_field)">
                                            <span class="dcf-available-field__icon" v-html="_field.icon"></span>
                                            <span class="dcf-available-field__title" v-html="_field.title"></span>
                                        </div>
                                    </column>
                                </columns>
                            </tab>
                            <tab name="Field Settings" :selected="showFieldOption">
                                <div class="dcf-field-settings">

                                    <field-options
                                            :options="fieldOptions"
                                    />

                                    <template v-for="_field in formFields" v-if="_field.id === activeField.field_type">
                                        <template v-for="(_setting,_id) in Object.assign(_field.settings)">
                                            <template v-if="'hidden' === _setting.type">
                                                <input type="hidden" v-model="activeField[_id]">
                                            </template>
                                            <div class="dcf-field-settings__control" v-else>
                                                <label class="dcf-field-settings__label">
                                                    <strong v-text="_setting.label"></strong>
                                                </label>
                                                <p class="description" v-if="_setting.description"
                                                   v-html="_setting.description"></p>
                                                <template v-if="'text' === _setting.type">
                                                    <input class="widefat" type="text" v-model="activeField[_id]">
                                                </template>
                                                <template v-else-if="'textarea' === _setting.type">
                                                    <textarea class="widefat" :rows="_setting.rows"
                                                              v-model="activeField[_id]"></textarea>
                                                </template>
                                                <template v-else-if="'select' === _setting.type">
                                                    <select class="widefat" v-model="activeField[_id]">
                                                        <option v-for="(label,value) in Object.assign(_setting.options)"
                                                                :value="value" v-text="label"></option>
                                                    </select>
                                                </template>
                                                <template v-else-if="'buttonset' === _setting.type">
                                                    <button-group v-model="activeField[_id]"
                                                                  :settings="buttonSetSettings(_setting)"></button-group>
                                                </template>
                                                <template v-else>
                                                    {{ _id }}
                                                    {{ _setting }}
                                                </template>
                                            </div>
                                        </template>
                                    </template>
                                </div>
                            </tab>
                        </tabs>
                    </column>
                </columns>
            </tab>
            <tab name="Form Actions">
                <columns>
                    <column :tablet="8">
                        <toggles>
                            <template v-for="(action,key) in actions">
                                <toggle v-for="_action in formActions" :name="_action.title" v-if="key === _action.id"
                                        :key="_action.id">
                                    {{ _action.settings }}
                                </toggle>
                            </template>
                        </toggles>
                    </column>
                    <column :tablet="4">
                        <h4>Available Actions</h4>
                        <div v-for="_action in formActions" :key="_action.id">
                            <button class="button">{{ _action.title }}</button>
                        </div>
                    </column>
                </columns>
            </tab>
            <tab name="Form Settings">
                <table class="form-table">
                    <tr v-for="_setting in formSettings">
                        <th>
                            <label :for="_setting.id" v-html="_setting.label"></label>
                        </th>
                        <td>
                            <template v-if="_setting.type === 'radio-button'">
                                <button-group :settings="_setting" v-model="settings[_setting.id]"></button-group>
                            </template>
                            <template v-else-if="_setting.type === 'select'">
                                <select class="regular-text" v-model="settings[_setting.id]">
                                    <option value="">-- Choose --</option>
                                    <option v-for="(label, value) in _setting.options" :value="value"
                                            v-text="label"></option>
                                </select>
                            </template>
                            <template v-else>
                                <input type="text" :id="_setting.id" v-model="settings[_setting.id]"/>
                            </template>
                            <p class="description" v-if="_setting.description" v-html="_setting.description"></p>
                        </td>
                    </tr>
                </table>
            </tab>
            <tab name="Validation Message">
                <table class="form-table">
                    <tr v-for="_message in formMessages">
                        <th>
                            <label :for="_message.id" v-html="_message.label"></label>
                        </th>
                        <td>
                            <textarea :id="_message.id" v-model="messages[_message.id]" rows="2" cols="35"></textarea>
                        </td>
                    </tr>
                </table>
            </tab>
        </tabs>
    </div>
</template>

<script>
import {CrudMixin} from "../../components/CrudMixin";
import {tabs, tab} from 'shapla-tabs';
import {toggles, toggle} from 'shapla-toggles';
import {columns, column} from 'shapla-columns'
import Field from "./Field";
import ButtonGroup from "../../components/ButtonGroup";
import draggable from 'vuedraggable'
import FieldOptions from "../../components/field-options/FieldOptions";

export default {
    name: "EditForm",
    mixins: [CrudMixin],
    components: {FieldOptions, ButtonGroup, Field, tabs, tab, columns, column, toggles, toggle, draggable},
    data() {
        return {
            id: 0,
            title: '',
            fields: [],
            actions: [],
            settings: [],
            messages: [],
            activeField: {},
            showFieldOption: false,
            fieldOptions: [{label: 'Options', value: 'Options', selected: false}]
        }
    },
    computed: {
        formActions() {
            return window.dialogContactForm.actions;
        },
        formFields() {
            return window.dialogContactForm.fields;
        },
        formSettings() {
            return window.dialogContactForm.settings;
        },
        formMessages() {
            return window.dialogContactForm.messages;
        },
        hasActiveField() {
            return Object.keys(this.activeField).length;
        }
    },
    mounted() {
        this.id = this.$route.params.id;
        this.$store.commit('SET_LOADING_STATUS', false);
        this.getForm();
    },
    methods: {
        onTabChange(name) {
            if (name === 'Field Settings') {
                if (!this.hasActiveField) {
                    this.activeField = this.fields[0];
                    this.showFieldOption = true;
                }
            } else {
                this.activeField = {};
                this.showFieldOption = false;
            }
        },
        getForm() {
            this.$store.commit('SET_LOADING_STATUS', true);
            this.get_item(dcfSettings.restRoot + '/forms/' + this.id).then(response => {
                console.log(response);
                this.title = response.title;
                this.fields = response.fields;
                this.actions = response.actions;
                this.settings = response.settings;
                this.messages = response.messages;
                this.$store.commit('SET_LOADING_STATUS', false);
            }).catch(error => {
                console.log(error);
                this.$store.commit('SET_LOADING_STATUS', false);
            })
        },
        isTextField(type) {
            return -1 !== ['text', 'email', 'url', 'date', 'password'].indexOf(type);
        },
        backToForms() {
            this.$router.push({name: 'FormsList'});
        },
        handleFieldAction(action, field) {
            let index = this.fields.indexOf(field);
            if ('edit' === action) {
                this.activeField = field;
                this.showFieldOption = true;
            }
            if ('delete' === action && confirm('Are you sure to delete?')) {
                this.$delete(this.fields, index);
            }
            if ('duplicate' === action && confirm('Are you sure to duplicate?')) {
                let _field = JSON.parse(JSON.stringify(field));
                _field['field_name'] = _field['field_id'] = field['field_id'] + '-copy';
                _field['field_title'] = field['field_title'] + ' Copy';
                this.fields.splice(index + 1, 0, _field);
            }
        },
        addNewField(field) {
            let data = {
                field_type: field.type,
                field_title: field.title + '',
                field_id: '',
                required_field: '',
                field_class: '',
                field_width: 'is-12',
                autocomplete: '',
                placeholder: '',
            };
            this.fields.push(data);
        },
        buttonSetSettings(_setting) {
            return {
                id: '',
                options: _setting.options,
            }
        },
    }
}
</script>

<style lang="scss">
.dcf-available-field {
    display: flex;
    border: 1px solid darkgreen;
    border-radius: 4px;

    &__icon,
    &__title {
        padding: 5px;
    }

    &__icon {
        padding-right: 5px;

        svg {
            display: block;
            height: 16px;
            width: 16px;
        }
    }

    &__title {
    }
}

.dcf-field-settings {
    background: #fff;
    padding: 10px;

    &__control {
        margin-bottom: 15px;
    }

    &__label {
        display: block;
        margin-bottom: 5px;
    }

    .dcf-button-group {
        display: flex;
    }
}
</style>