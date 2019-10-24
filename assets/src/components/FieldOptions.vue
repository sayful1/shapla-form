<template>
    <div class="field-option-data">
        <div class="panel-field-opt panel-field-opt-text">
            <label class="field-option-data__label clearfix">
                <strong>{{ option_field.title }}</strong>
                <help-text v-if="option_field.help_text" :text="option_field.help_text"></help-text>
                <span class="pull-right">
                    <input type="checkbox" v-model="show_value">Show values
                </span>
            </label>

            <ul :class="['option-field-option-chooser', show_value ? 'show-value' : '']">

                <li class="clearfix margin-0 header option-field-option">
                    <div class="option-field-option__selector">&nbsp;</div>

                    <div class="option-field-option__sort-handler">&nbsp;</div>

                    <div class="option-field-option__label">Label</div>

                    <div v-if="show_value" class="option-field-option__value">Value</div>

                    <div class="option-field-option__action-buttons">&nbsp;</div>
                </li>
            </ul>

            <ul :class="['option-field-option-chooser margin-0', show_value ? 'show-value' : '']">
                <li v-for="(option, index) in options" :key="option.id" :data-index="index"
                    class="clearfix option-field-option">
                    <div class="option-field-option__selector">
                        <input
                                v-if="option_field.is_multiple"
                                type="checkbox"
                                :value="option.value"
                                v-model="selected"
                        >
                        <input
                                v-else
                                type="radio"
                                :value="option.value"
                                v-model="selected"
                                class="option-chooser-radio"
                        >
                    </div>

                    <div class="option-field-option__sort-handler">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <path d="M0 0h24v24H0z" fill="none"/>
                            <path d="M3 15h18v-2H3v2zm0 4h18v-2H3v2zm0-8h18V9H3v2zm0-6v2h18V5H3z"/>
                        </svg>
                    </div>

                    <div class="option-field-option__label">
                        <input type="text" v-model="option.label" @input="set_option_label(index, option.label)">
                    </div>

                    <div v-if="show_value" class="option-field-option__value">
                        <input type="text" v-model="option.value">
                    </div>

                    <div class="action-buttons clearfix option-field-option__button">
                        <span @click="delete_option(index)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                                <path d="M0 0h24v24H0z" fill="none"/>
                                <path d="M7 11v2h10v-2H7zm5-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                            </svg>
                        </span>
                    </div>
                </li>
                <li>
                    <div class="plus-buttons clearfix" @click="add_option">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <path d="M0 0h24v24H0z" fill="none"/>
                            <path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h-4V7zm-1-5C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                        </svg>
                    </div>
                </li>
            </ul>

            <a v-if="!option_field.is_multiple && selected" href="#clear" @click.prevent="clear_selection">Clear
                Selection</a>
        </div>
    </div>
</template>

<script>
    export default {
        name: "FieldOptions",
        props: {
            option_field: {
                type: Object, default: () => {
                }
            },
            options: {
                type: Array, default: () => [
                    {id: 1, label: 'One', value: 'One'},
                    {id: 2, label: 'Two', value: 'Two'},
                    {id: 3, label: 'Three', value: 'Three'},
                ]
            },
            selected: null,
        },
        data() {
            return {
                show_value: false,
            }
        },
        methods: {
            add_option() {
            },
            set_option_label(index, label) {
            },
            delete_option(index) {
            },
            clear_selection() {
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

    .option-field-option {
        display: flex;
        justify-content: center;
        align-items: center;

        &__selector,
        &__sort-handler,
        &__button {
            width: 24px;
            height: 24px;
        }

        &__selector {
        }

        &__sort-handler {
        }

        &__label,
        &__value {
            width: calc(100% - 72px);
            margin-right: 8px;

            .option-field-option-chooser.show-value & {
                width: calc(50% - 72px / 2);
                // width: 100px;
            }

            input {
                width: 100% !important;
            }
        }

        &__button {
        }
    }
</style>