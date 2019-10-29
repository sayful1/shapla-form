<template>
    <div class="wrap dialog-contact-form-app">
        <router-view></router-view>
        <notification :options="notification"></notification>
        <confirm-dialog></confirm-dialog>
        <spinner :active="loading"></spinner>
    </div>
</template>

<script>
    import spinner from 'shapla-spinner';
    import notification from 'shapla-notifications';
    import {ConfirmDialog} from 'shapla-confirm-modal';
    import {mapState} from 'vuex';

    export default {
        name: 'App',
        components: {notification, spinner, ConfirmDialog},
        computed: {
            ...mapState(['loading', 'notification']),
        }
    }
</script>

<style lang="scss">
    @import "admin";
</style>
