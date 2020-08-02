<template>
    <div class="shapla-field-option" :class="{'showing-value':showValue}">

        <div class="shapla-field-option__selector">
            <input
                    :type="multiple ? 'checkbox' : 'radio'"
                    :checked="localOption.selected"
                    @click="mark_selected"
            >
        </div>

        <div class="shapla-field-option__sort-handler">
            <icon-container :hoverable="true" size="medium">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M0 0h24v24H0z" fill="none"/>
                    <path d="M3 15h18v-2H3v2zm0 4h18v-2H3v2zm0-8h18V9H3v2zm0-6v2h18V5H3z"/>
                </svg>
            </icon-container>
        </div>

        <div class="shapla-field-option__label">
            <input type="text" :value="localOption.label" @input="set_option_label($event.target.value)">
        </div>

        <div v-if="showValue" class="shapla-field-option__value">
            <input type="text" :value="localOption.value" @input="set_option_value($event.target.value)">
        </div>

        <div class="action-buttons clearfix shapla-field-option__button">
            <icon-container :hoverable="true" size="medium" @click="delete_option">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 11v2h10v-2H7zm5-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                </svg>
            </icon-container>
        </div>
    </div>
</template>

<script>
import iconContainer from 'shapla-icon-container';

export default {
    name: "FieldOption",
    components: {iconContainer},
    props: {
        index: {type: [Number, String], default: 0},
        option: {type: Object, required: true},
        multiple: {type: Boolean, default: false},
        showValue: {type: Boolean, default: false}
    },
    data() {
        return {
            localOption: {}
        }
    },
    watch: {
        option: {
            handler(newValue) {
                this.localOption = newValue;
            },
            deep: true
        }
    },
    mounted() {
        this.setLocalOption(this.option);
    },
    methods: {
        setLocalOption(option) {
            this.localOption = Object.assign({label: '', value: '', selected: false}, option);
        },
        set_option_label(label) {
            this.localOption.label = label;

            if (!this.showValue) {
                this.set_option_value(label);
            }

            this.trigger_change();
        },
        set_option_value(value) {
            this.localOption.value = value;

            this.trigger_change();
        },
        mark_selected() {
            this.$emit('select', this.index, !this.localOption.selected);
        },
        delete_option() {
            this.$emit('delete', this.index, this.option);
        },
        trigger_change() {
            this.$emit('input', this.localOption, this.index);
        }
    }
}
</script>

<style lang="scss">
.shapla-field-option {
    display: flex;
    justify-content: center;
    align-items: center;

    &__selector,
    &__sort-handler,
    &__button {
        width: 2em;
        height: 2em;
    }

    &__selector {
    }

    &__sort-handler {
    }

    &__label,
    &__value {
        width: calc(100% - 6em);
        margin-right: 8px;

        .shapla-field-option.showing-value & {
            width: calc(50% - 6em / 2);
        }

        input {
            width: 100% !important;
        }
    }

    &__button {
    }
}
</style>