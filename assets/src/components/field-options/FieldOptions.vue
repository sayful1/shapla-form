<template>
    <div class="field-option-data">
        <div class="panel-field-opt panel-field-opt-text">
            <label class="field-option-data__label clearfix">
                <strong>{{ label }}</strong>
                <span v-if="help_text" :text="help_text"></span>
            </label>

            <ul :class="['option-field-option-chooser', show_value ? 'show-value' : '']">

                <li class="clearfix margin-0 header option-field-option">
                    <div class="shapla-field-option" :class="{'showing-value':show_value}">
                        <div class="shapla-field-option__selector">&nbsp;</div>
                        <div class="shapla-field-option__sort-handler">&nbsp;</div>
                        <div class="shapla-field-option__label">
                            <strong>Label</strong>
                        </div>
                        <div class="shapla-field-option__value" v-if="show_value">
                            <strong>Value</strong>
                        </div>
                        <div class="shapla-field-option__action-buttons">&nbsp;</div>
                    </div>
                </li>
            </ul>

            <ul :class="['option-field-option-chooser margin-0', show_value ? 'show-value' : '']">
                <li v-for="(option, index) in localOptions" :key="index" class="clearfix option-field-option">
                    <field-option
                            v-if="Object.keys(option).length"
                            :index="index"
                            :option="option"
                            :show-value="show_value"
                            :multiple="multiple"
                            @input="handleOptionChange"
                            @select="handleOptionSelect"
                            @delete="handleOptionDelete"
                    />
                </li>
                <li>
                    <div class="shapla-field-option-actions">
                        <span class="pull-left">
                            <label>
                                <input type="checkbox" v-model="show_value">
                                Show values
                            </label>
                        </span>
                        <span class="shapla-field-option-actions__spacer"></span>
                        <span class="pull-right">
                            <a v-if="hasSelectedItem" href="#" @click.prevent="clear_selection">Clear Selection</a>
                        </span>
                    </div>
                </li>
                <li>
                    <div class="plus-buttons clearfix" @click="add_option">
                        <icon-container>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path d="M0 0h24v24H0z" fill="none"/>
                                <path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                            </svg>
                        </icon-container>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
import iconContainer from 'shapla-icon-container';
import FieldOption from "./FieldOption";

export default {
    name: "FieldOptions",
    components: {FieldOption, iconContainer},
    props: {
        label: {type: String, default: 'Options'},
        help_text: {type: String, required: false},
        multiple: {type: Boolean, default: false},
        options: {type: Array, default: () => []},
        selected: {type: [Array, String, Number], default: null},
    },
    data() {
        return {
            show_value: false,
            localOptions: [],
        }
    },
    watch: {
        options: {
            handler(newValue) {
                newValue.forEach((option, index) => {
                    this.localOptions[index] = Object.assign({label: '', value: '', selected: false}, option);
                })
            },
            deep: true
        },
        localOptions: {
            handler(newValue) {
                this.$emit('input', newValue);
            },
            deep: true
        }
    },
    mounted() {
        this.options.forEach((option, index) => {
            this.localOptions[index] = Object.assign({label: '', value: '', selected: false}, option);
        })
    },
    computed: {
        hasSelectedItem() {
            if (this.localOptions.length < 1) {
                return false;
            }
            let items = this.localOptions.find(_option => _option.selected === true);

            return !!(typeof items === "object" && Object.keys(items).length);
        }
    },
    methods: {
        emitInputEvent(value) {
            this.$emit('input', value);
        },
        add_option() {
            let num = this.localOptions.length;
            this.localOptions.push({label: `Option ${num + 1}`, value: `Option ${num + 1}`, selected: false})
        },
        clear_selection() {
            this.localOptions.forEach(option => {
                option.selected = false;
            })
        },
        handleOptionChange(newValue, index) {
            this.localOptions[index] = newValue;
        },
        handleOptionSelect(index, selected) {
            this.localOptions[index].selected = selected;

            if (!this.multiple) {
                this.localOptions.forEach((option, _index) => {
                    if (_index !== index) {
                        this.localOptions[_index].selected = false;
                    }
                })
            }
        },
        handleOptionDelete(index) {
            this.$delete(this.localOptions, index);
        }
    }
}
</script>

<style lang="scss">
.field-option-data {
    &__label {
        display: flex;
        justify-content: space-between;
    }
}

.margin-0 {
    margin: 0 !important;
}

.plus-buttons {
    border: 1px solid rgba(0, 0, 0, .6);
    display: flex;
    justify-content: center;
    padding: 3px;
}

.shapla-field-option-actions {
    display: flex;
    margin: 10px 0;

    &__spacer {
        flex-grow: 1;
    }
}
</style>