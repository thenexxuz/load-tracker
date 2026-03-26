const strapQtyByRackQty: Record<number, number> = {
  1: 3,
  2: 5,
  3: 8,
  4: 10,
  5: 13,
  6: 15,
  7: 18,
  8: 20,
  9: 23,
  10: 25,
  11: 28,
  12: 33,
  13: 36,
  14: 38,
  15: 41,
  16: 43,
  17: 43,
  18: 45,
  19: 48,
  20: 50,
}

export function getShipmentEquipmentDefaults(rackQty: number | null | undefined): { loadBarQty: number; strapQty: number } {
  const normalizedRackQty = Math.max(0, Math.min(Math.trunc(Number(rackQty) || 0), 20))

  if (normalizedRackQty === 0) {
    return {
      loadBarQty: 0,
      strapQty: 0,
    }
  }

  return {
    loadBarQty: 2,
    strapQty: strapQtyByRackQty[normalizedRackQty],
  }
}