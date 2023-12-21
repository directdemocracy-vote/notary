function encodeBase128(byteArray) { // Uint8Array
  const base128Charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' +
    'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõøùúûüýÿþ@$*£¢';
  function toBin(byteArray) {
    let end = '';
    for (let i in byteArray) {
      let aux = byteArray[i].toString(2);
      if (aux.length < 8)
        aux = '0'.repeat(8 - aux.length) + aux;
      end += aux;
    }
    return end;
  }
  const bin = toBin(byteArray);
  const sevenBits = bin.match(/.{1,7}/g);
  while (sevenBits[sevenBits.length - 1].length < 7)
    sevenBits[sevenBits.length - 1] += '0';
  let res = [];
  for (let i in sevenBits) {
    const interger = parseInt('0' + sevenBits[i], 2);
    res.push(base128Charset[interger]);
  }
  res = res.join('');
  return res;
}
