<template>
    <div>
        <h1 class="wp-heading-inline">Forms</h1>
        <a href="#" class="page-title-action" @click.prevent="openAddNewModal = true">Add New</a>
        <hr class="wp-header-end">
        <columns :multiline="true">
            <column :tablet="6">
                <status-list :statuses="metaData.statuses" @change="changeStatus"></status-list>
            </column>
            <column :tablet="2"></column>
            <column :tablet="4">
                <search-form @search:submit="handleSearchSubmit" @search:input="handleSearchInput"></search-form>
            </column>
            <column :tablet="12">
                <data-table
                        :items="items"
                        :columns="columns"
                        :actions="actions"
                        :bulk-actions="bulkActions"
                        :action-column="metaData.primaryColumn"
                        @action:click="handleAction"
                        @bulk:apply="handleBulkAction"
                        @search:submit="handleSearchSubmit"
                        @search:input="handleSearchInput"
                >
                    <template slot="created_at" slot-scope="item">
                        {{(new Date(item.row.created_at)).toLocaleString()}}
                    </template>
                    <template slot="shortcode" slot-scope="item">
                        <copy-to-clipboard :value="item.row.shortcode">{{item.row.shortcode}}</copy-to-clipboard>
                    </template>
                </data-table>
            </column>
            <column :tablet="12">
                <pagination :current_page="currentPage" :per_page="pagination.per_page"
                            :total_items="pagination.total_items" @pagination="goToPage"></pagination>
            </column>
        </columns>
        <modal :active="openAddNewModal" @close="openAddNewModal = false" content-size="large" title="Form Templates">
            <columns :multiline="true">
                <column :tablet="6" v-for="_template in templates" :key="_template.id">
                    <div class="dcf-template-box" :class="{'is-selected':_template === selectedTemplate}"
                         @click="selectedTemplate = _template">
                        <h3 class="dcf-template-box__title" v-html="_template.title"></h3>
                        <p class="dcf-template-box__description" v-html="_template.description"></p>
                    </div>
                </column>
            </columns>
            <template slot="foot">
                <button class="button" @click="openAddNewModal = false">Close</button>
                &nbsp;&nbsp;
                <button class="button button-primary" v-if="Object.keys(selectedTemplate).length"
                        @click.prevent="createNewForm">Create New Form
                </button>
            </template>
        </modal>
    </div>
</template>

<script>
    import {CrudMixin} from "../../components/CrudMixin";
    import modal from 'shapla-modal'
    import {columns, column} from 'shapla-columns'
    import dataTable from 'shapla-data-table';
    import statusList from "shapla-data-table-status";
    import pagination from "shapla-data-table-pagination";
    import searchForm from "shapla-search-form";
    import CopyToClipboard from "../../components/CopyToClipboard";

    export default {
        name: "FormsList",
        mixins: [CrudMixin],
        components: {CopyToClipboard, statusList, dataTable, modal, columns, column, pagination, searchForm},
        data() {
            return {
                openAddNewModal: false,
                templates: [],
                selectedTemplate: {},
                status: 'all',
                search: '',
                currentPage: 1,
                columns: [
                    {key: 'title', label: 'Title'},
                    {key: 'shortcode', label: 'Shortcode'},
                    {key: 'entries', label: 'Entries'},
                ],
                metaData: {
                    statuses: [],
                    actions: [],
                    bulk_actions: [],
                    primaryColumn: 'title',
                },
            }
        },

        computed: {
            actions() {
                return this.metaData.actions;
            },
            bulkActions() {
                return this.metaData.bulk_actions;
            }
        },
        mounted() {
            this.$store.commit('SET_LOADING_STATUS', false);
            this.templates = window.dialogContactForm.templates;
            this.getForms();
        },
        methods: {
            getForms() {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.get_items(dcfSettings.restRoot + '/forms/', {
                    params: {
                        page: this.currentPage,
                        status: this.status,
                        search: this.search,
                        metadata: true,
                    }
                }).then(data => {
                    this.metaData = data.metaData;
                    this.columns = this.metaData.columns;
                    this.$store.commit('SET_LOADING_STATUS', false);
                }).catch(error => {
                    console.log(error);
                    this.$store.commit('SET_LOADING_STATUS', false);
                })
            },
            changeStatus(status) {
                this.status = status.key;
                this.search = '';
                this.currentPage = 1;
                // this.$router.push({name: 'EntriesList', params: {status: status.key}});
                this.getForms();
            },
            goToPage(page) {
                this.currentPage = page;
                this.getForms();
            },
            createNewForm() {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.create_item(dcfSettings.restRoot + '/forms', {
                    template: this.selectedTemplate.id,
                }).then(data => {
                    console.log(data);
                    this.$store.commit('SET_LOADING_STATUS', false);
                    this.openAddNewModal = false;
                    this.getForms();
                }).catch(error => {
                    console.log(error);
                    this.$store.commit('SET_LOADING_STATUS', false);
                })
            },
            handleSearchSubmit(text) {
                this.search = text;
                this.getForms();
            },
            handleSearchInput(text) {
                if (text.length < 1) {
                    this.search = '';
                    this.getForms();
                }
            },
            handleAction(action, item) {
                if ('edit' === action) {
                    this.$router.push({name: 'EditForm', params: {id: item.id}});
                    // window.location.href = item.edit_url;
                }
                if (-1 !== ['trash', 'restore', 'delete'].indexOf(action)) {
                    let message = 'Are you sure to do this?';
                    if ('trash' === action) message = 'Are you sure move this item to trash?';
                    if ('restore' === action) message = 'Are you sure restore this item again?';
                    if ('delete' === action) message = 'Are you sure to delete permanently?';
                    this.$modal.confirm(message).then(confirmed => {
                        if (confirmed) {
                            this.batchTrashAction([item.id], action);
                        }
                    });
                }
            },
            handleBulkAction(action, ids) {
                if (-1 !== ['trash', 'restore', 'delete'].indexOf(action)) {
                    let message = 'Are you sure to do this?';
                    if ('trash' === action) message = 'Are you sure to trash all selected items?';
                    if ('restore' === action) message = 'Are you sure to restore all selected items?';
                    if ('delete' === action) message = 'Are you sure to delete all selected items permanently?';
                    this.$modal.confirm(message).then(confirmed => {
                        if (confirmed) {
                            this.batchTrashAction(ids, action);
                        }
                    });
                }
            },
            batchTrashAction(ids, action) {
                this.$store.commit('SET_LOADING_STATUS', true);
                this.action_batch_trash(dcfSettings.restRoot + '/forms/batch', ids, action).then(() => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    this.getForms();
                }).catch(error => {
                    this.$store.commit('SET_LOADING_STATUS', false);
                    console.log(error);
                });
            }
        }
    }
</script>

<style lang="scss">
    .dcf-template-box {
        border: 1px solid rgba(0, 0, 0, .06);
        border-radius: 4px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        height: 100%;
        padding: 1rem;

        &.is-selected {
            background-color: #1ea9ea;
            border-color: #1ea9ea;
            color: #fff;
        }

        &:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        &__title {
            margin: 0 0 15px;
            font-size: 20px;
            font-weight: 500;
            color: currentColor;
        }

        &__description {
            margin: 0;
            font-size: 16px;
        }
    }
</style>