import { ref, watch } from 'vue'

import { getShipmentEquipmentDefaults } from '@/lib/shipmentEquipmentDefaults'

type ShipmentEquipmentForm = {
  rack_qty: number | null
  load_bar_qty: number
  strap_qty: number
}

export function useShipmentEquipmentDefaults(form: ShipmentEquipmentForm): void {
  const lastSuggestedEquipment = ref(getShipmentEquipmentDefaults(form.rack_qty))

  watch(() => form.rack_qty, (rackQty) => {
    const nextSuggestedEquipment = getShipmentEquipmentDefaults(rackQty)

    if (form.load_bar_qty === lastSuggestedEquipment.value.loadBarQty || form.load_bar_qty === 0) {
      form.load_bar_qty = nextSuggestedEquipment.loadBarQty
    }

    if (form.strap_qty === lastSuggestedEquipment.value.strapQty || form.strap_qty === 0) {
      form.strap_qty = nextSuggestedEquipment.strapQty
    }

    lastSuggestedEquipment.value = nextSuggestedEquipment
  })
}