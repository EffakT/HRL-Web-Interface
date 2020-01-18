<template>
    <button :class="classes" @click="click(data)" title="Update">
        {{ name }}
    </button>
</template>
<script>
    export default {
        props: {
            data: {},
            name: {},
            click: {},
            meta: {},
            classes: {},
        }
    }
</script>
