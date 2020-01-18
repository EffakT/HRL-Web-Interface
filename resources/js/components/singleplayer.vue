<template>
    <data-table
        :columns="columns"
        :url="ajax"
        :classes="classes"
        order-by="time">
    </data-table>
</template>

<script>
    // import viewButton from "./viewButton";

    export default {
        props: {
            //route: {type: String, required: true},
            ajax: {type: String, required: true}
        },
        mounted() {
        },
        data() {
            return {
                columns: [
                    {
                        label: 'Map',
                        name: 'map_name',
                        orderable: true,
                    },
                    {
                        label: 'Server',
                        name: 'server_name',
                        orderable: true,
                    },
                    {
                        label: 'IP',
                        name: 'ip',
                        orderable: true,
                    },
                    {
                        label: 'Port',
                        name: 'port',
                        orderable: true,
                    },
                    {
                        label: 'Time (seconds)',
                        name: 'time',
                        orderable: true,
                    },
                    {
                        label: 'Date of Lap',
                        name: 'created_at',
                        orderable: true,
                    },
                    /*{
                        label: '',
                        name: 'View',
                        orderable: false,
                        classes: {
                            'btn': true,
                            'btn-primary': true,
                            'btn-sm': true
                        },
                        event: "click",
                        handler: this.onClick,
                        component: viewButton,
                    }*/
                ],
                classes: {
                    "table-container": {
                        "table-responsive": true,
                    },
                    "table": {
                        "table": true,
                        "table-striped": false,
                        "table-dark": false,
                        "table-hover": true,
                    },
                    "t-head": {},
                    "t-body": {},
                    "t-head-tr": {},
                    "t-body-tr": {},
                    "td": {},
                    "th": {},
                }
            }
        },
        /*components: {
            viewButton,
        },*/
        methods: {
            /*onClick(data) {
                var url = this.route.replace('server_id', data.id);
                window.location.href = url;
            },*/
        },
    }
</script>
