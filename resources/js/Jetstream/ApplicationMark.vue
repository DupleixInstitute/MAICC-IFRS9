<template>
    <img alt="Logo" v-if="logoUrl" :src="logoUrl" class="w-auto"/>
</template>
<script>
export default {
    props: {
        smallLogo: {
            default: false
        }
    },
    data() {
        return {
            logoUrl: ''
        }
    },
    mounted() {
        if (this.smallLogo) {
            this.logoUrl = this.$page.props.smallLogoUrl;
        }else{
            this.logoUrl = this.$page.props.logoUrl;
        }
    }
}
</script>
